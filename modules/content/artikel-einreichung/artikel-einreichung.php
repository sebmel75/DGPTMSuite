<?php
/**
 * Plugin Name: DGPTM - Artikel-Einreichung Perfusiologie
 * Description: Einreichungssystem für wissenschaftliche Artikel der Fachzeitschrift "Die Perfusiologie" mit Peer-Review-Workflow
 * Version: 1.0.0
 * Author: Sebastian Melzer
 * Text Domain: dgptm-artikel-einreichung
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('DGPTM_Artikel_Einreichung')) {

    class DGPTM_Artikel_Einreichung {

        private static $instance = null;

        // Constants
        const POST_TYPE = 'artikel_einreichung';
        const OPT_REVIEWERS = 'dgptm_artikel_reviewers';
        const OPT_REVIEWER_NOTES = 'dgptm_artikel_reviewer_notes';
        const OPT_TEXT_SNIPPETS = 'dgptm_artikel_text_snippets';
        const OPT_SETTINGS = 'dgptm_artikel_settings';
        const NONCE_ACTION = 'dgptm_artikel_nonce';

        // Article Status
        const STATUS_SUBMITTED = 'eingereicht';
        const STATUS_FORMAL_CHECK = 'formale_pruefung';
        const STATUS_UNDER_REVIEW = 'in_review';
        const STATUS_REVISION_REQUIRED = 'revision_erforderlich';
        const STATUS_REVISION_SUBMITTED = 'revision_eingereicht';
        const STATUS_ACCEPTED = 'angenommen';
        const STATUS_REJECTED = 'abgelehnt';
        const STATUS_PUBLISHED = 'veroeffentlicht';

        // Publikationsarten (matching existing ACF)
        const PUBLIKATIONSARTEN = [
            'originalarbeit' => 'Originalarbeit',
            'uebersichtsarbeit' => 'Übersichtsarbeit',
            'fallbericht' => 'Fallbericht',
            'kurzmitteilung' => 'Kurzmitteilung',
            'kommentar' => 'Kommentar',
            'editorial' => 'Editorial',
            'tutorial' => 'Tutorial',
            'abstract' => 'Abstract (Jahrestagung)'
        ];

        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            $this->define_constants();

            // Hooks
            add_action('init', [$this, 'register_post_type']);
            add_action('init', [$this, 'register_shortcodes']);
            add_action('admin_menu', [$this, 'add_admin_menus']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
            add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);

            // AJAX Handlers (logged in users)
            add_action('wp_ajax_dgptm_submit_artikel', [$this, 'ajax_submit_artikel']);
            add_action('wp_ajax_dgptm_assign_reviewer', [$this, 'ajax_assign_reviewer']);
            add_action('wp_ajax_dgptm_submit_review', [$this, 'ajax_submit_review']);
            add_action('wp_ajax_dgptm_update_artikel_status', [$this, 'ajax_update_status']);
            add_action('wp_ajax_dgptm_submit_revision', [$this, 'ajax_submit_revision']);
            add_action('wp_ajax_dgptm_save_reviewer_list', [$this, 'ajax_save_reviewer_list']);
            add_action('wp_ajax_dgptm_editor_decision', [$this, 'ajax_editor_decision']);
            add_action('wp_ajax_dgptm_save_editor_notes', [$this, 'ajax_save_editor_notes']);
            add_action('wp_ajax_dgptm_search_users', [$this, 'ajax_search_users']);
            add_action('wp_ajax_dgptm_add_reviewer', [$this, 'ajax_add_reviewer']);
            add_action('wp_ajax_dgptm_remove_reviewer', [$this, 'ajax_remove_reviewer']);
            add_action('wp_ajax_dgptm_save_reviewer_notes', [$this, 'ajax_save_reviewer_notes']);
            add_action('wp_ajax_dgptm_preview_email', [$this, 'ajax_preview_email']);
            add_action('wp_ajax_dgptm_send_custom_email', [$this, 'ajax_send_custom_email']);
            add_action('wp_ajax_dgptm_get_text_snippets', [$this, 'ajax_get_text_snippets']);
            add_action('wp_ajax_dgptm_save_text_snippet', [$this, 'ajax_save_text_snippet']);
            add_action('wp_ajax_dgptm_delete_text_snippet', [$this, 'ajax_delete_text_snippet']);
            add_action('wp_ajax_dgptm_save_artikel_settings', [$this, 'ajax_save_artikel_settings']);

            // AJAX Handlers for non-logged in users (token-based)
            add_action('wp_ajax_nopriv_dgptm_submit_artikel', [$this, 'ajax_submit_artikel']);
            add_action('wp_ajax_dgptm_submit_revision_token', [$this, 'ajax_submit_revision_token']);
            add_action('wp_ajax_nopriv_dgptm_submit_revision_token', [$this, 'ajax_submit_revision_token']);

            // ACF Fields
            add_action('acf/init', [$this, 'register_acf_fields']);

            // Testdata generator
            add_action('admin_init', [$this, 'handle_testdata_generation']);

            // PDF Download handler
            add_action('admin_init', [$this, 'handle_pdf_download']);
            add_action('template_redirect', [$this, 'handle_frontend_pdf_download']);

            // Admin notices
            add_action('admin_notices', [$this, 'show_admin_notices']);
        }

        /**
         * Show admin notices (e.g., after testdata generation)
         */
        public function show_admin_notices() {
            if (isset($_GET['testdata_created']) && intval($_GET['testdata_created']) > 0) {
                $count = intval($_GET['testdata_created']);
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p><strong>Artikel-Einreichung:</strong> ' . $count . ' Testdatensätze wurden erfolgreich erstellt.</p>';
                echo '</div>';
            }
        }

        /**
         * Handle testdata generation request
         */
        public function handle_testdata_generation() {
            if (!isset($_GET['generate_testdata']) || $_GET['generate_testdata'] !== '1') {
                return;
            }

            if (!current_user_can('manage_options')) {
                return;
            }

            // Include the generator file
            $generator_file = DGPTM_ARTIKEL_PATH . 'generate-testdata.php';
            if (file_exists($generator_file)) {
                require_once $generator_file;

                // Function is defined in the generator file
                if (function_exists('dgptm_generate_artikel_testdata')) {
                    $count = isset($_GET['count']) ? intval($_GET['count']) : 5;
                    $results = dgptm_generate_artikel_testdata($count);

                    // Redirect back with success message
                    wp_redirect(admin_url('edit.php?post_type=' . self::POST_TYPE . '&testdata_created=' . count($results)));
                    exit;
                }
            }
        }

        /**
         * Handle PDF download in admin
         */
        public function handle_pdf_download() {
            if (!isset($_GET['dgptm_artikel_pdf']) || !isset($_GET['artikel_id'])) {
                return;
            }

            $artikel_id = intval($_GET['artikel_id']);

            if (!current_user_can('manage_options') && !$this->is_editor_in_chief()) {
                wp_die('Keine Berechtigung.');
            }

            $this->generate_artikel_pdf($artikel_id);
            exit;
        }

        /**
         * Handle PDF download in frontend (for authors with token)
         */
        public function handle_frontend_pdf_download() {
            if (!isset($_GET['dgptm_artikel_pdf']) || !isset($_GET['artikel_id'])) {
                return;
            }

            $artikel_id = intval($_GET['artikel_id']);
            $token = isset($_GET['autor_token']) ? sanitize_text_field($_GET['autor_token']) : '';

            // Check if user is logged in and author, or has valid token
            $article = get_post($artikel_id);
            if (!$article || $article->post_type !== self::POST_TYPE) {
                wp_die('Artikel nicht gefunden.');
            }

            $is_author = is_user_logged_in() && $article->post_author == get_current_user_id();
            $is_valid_token = $token && $this->validate_author_token($token) === $artikel_id;
            $is_editor = $this->is_editor_in_chief();

            if (!$is_author && !$is_valid_token && !$is_editor && !current_user_can('manage_options')) {
                wp_die('Keine Berechtigung.');
            }

            $this->generate_artikel_pdf($artikel_id);
            exit;
        }

        /**
         * Generate PDF for article
         */
        private function generate_artikel_pdf($artikel_id) {
            $article = get_post($artikel_id);
            if (!$article) {
                wp_die('Artikel nicht gefunden.');
            }

            // Include FPDF
            $fpdf_path = dirname(dirname(dirname(dirname(__FILE__)))) . '/libraries/fpdf/fpdf.php';
            if (!file_exists($fpdf_path)) {
                wp_die('PDF-Bibliothek nicht gefunden.');
            }
            require_once $fpdf_path;

            // Get article data
            $submission_id = get_field('submission_id', $artikel_id);
            $title = $article->post_title;
            $author_name = get_field('hauptautorin', $artikel_id);
            $author_email = get_field('hauptautor_email', $artikel_id);
            $institution = get_field('hauptautor_institution', $artikel_id);
            $co_authors = get_field('autoren', $artikel_id);
            $publikationsart = self::PUBLIKATIONSARTEN[get_field('publikationsart', $artikel_id)] ?? '';
            $abstract_de = get_field('abstract-deutsch', $artikel_id);
            $abstract_en = get_field('abstract', $artikel_id);
            $keywords_de = get_field('keywords-deutsch', $artikel_id);
            $keywords_en = get_field('keywords', $artikel_id);
            $status = $this->get_status_label(get_field('artikel_status', $artikel_id));
            $submitted_at = get_field('submitted_at', $artikel_id);

            // Create PDF
            $pdf = new \FPDF('P', 'mm', 'A4');
            $pdf->AddPage();
            $pdf->SetMargins(20, 20, 20);

            // Header
            $pdf->SetFont('Helvetica', 'B', 18);
            $pdf->SetTextColor(26, 54, 93); // #1a365d
            $pdf->Cell(0, 10, 'Die Perfusiologie', 0, 1, 'C');
            $pdf->Ln(10);

            // Submission ID
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->Cell(0, 5, 'Einreichungs-ID: ' . $this->utf8_decode_safe($submission_id), 0, 1, 'R');
            $pdf->Cell(0, 5, 'Status: ' . $this->utf8_decode_safe($status), 0, 1, 'R');
            $pdf->Cell(0, 5, 'Eingereicht: ' . date_i18n('d.m.Y H:i', strtotime($submitted_at)), 0, 1, 'R');
            $pdf->Ln(5);

            // Title
            $pdf->SetFont('Helvetica', 'B', 14);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->MultiCell(0, 7, $this->utf8_decode_safe($title), 0, 'L');
            $pdf->Ln(3);

            // Publication type
            $pdf->SetFont('Helvetica', 'I', 10);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->Cell(0, 5, 'Publikationsart: ' . $this->utf8_decode_safe($publikationsart), 0, 1);
            $pdf->Ln(5);

            // Author info
            $pdf->SetFont('Helvetica', 'B', 11);
            $pdf->SetTextColor(26, 54, 93);
            $pdf->Cell(0, 6, 'Korrespondenzautor', 0, 1);
            $pdf->SetFont('Helvetica', '', 10);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(0, 5, $this->utf8_decode_safe($author_name), 0, 1);
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->Cell(0, 5, $this->utf8_decode_safe($author_email), 0, 1);
            if ($institution) {
                $pdf->Cell(0, 5, $this->utf8_decode_safe($institution), 0, 1);
            }
            $pdf->Ln(3);

            // Co-authors
            if ($co_authors) {
                $pdf->SetFont('Helvetica', 'B', 11);
                $pdf->SetTextColor(26, 54, 93);
                $pdf->Cell(0, 6, 'Ko-Autoren', 0, 1);
                $pdf->SetFont('Helvetica', '', 10);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->MultiCell(0, 5, $this->utf8_decode_safe($co_authors), 0, 'L');
                $pdf->Ln(3);
            }

            // Abstract German
            if ($abstract_de) {
                $pdf->SetFont('Helvetica', 'B', 11);
                $pdf->SetTextColor(26, 54, 93);
                $pdf->Cell(0, 6, 'Abstract (Deutsch)', 0, 1);
                $pdf->SetFont('Helvetica', '', 10);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->MultiCell(0, 5, $this->utf8_decode_safe($abstract_de), 0, 'J');
                $pdf->Ln(3);
            }

            // Keywords German
            if ($keywords_de) {
                $pdf->SetFont('Helvetica', 'I', 9);
                $pdf->SetTextColor(100, 100, 100);
                $pdf->Cell(0, 5, 'Keywords: ' . $this->utf8_decode_safe($keywords_de), 0, 1);
                $pdf->Ln(3);
            }

            // Abstract English
            if ($abstract_en) {
                $pdf->SetFont('Helvetica', 'B', 11);
                $pdf->SetTextColor(26, 54, 93);
                $pdf->Cell(0, 6, 'Abstract (English)', 0, 1);
                $pdf->SetFont('Helvetica', '', 10);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->MultiCell(0, 5, $this->utf8_decode_safe($abstract_en), 0, 'J');
                $pdf->Ln(3);
            }

            // Keywords English
            if ($keywords_en) {
                $pdf->SetFont('Helvetica', 'I', 9);
                $pdf->SetTextColor(100, 100, 100);
                $pdf->Cell(0, 5, 'Keywords: ' . $this->utf8_decode_safe($keywords_en), 0, 1);
            }

            // Footer
            $pdf->SetY(-30);
            $pdf->SetFont('Helvetica', '', 8);
            $pdf->SetTextColor(150, 150, 150);
            $pdf->Cell(0, 5, 'Generiert am ' . date_i18n('d.m.Y H:i') . ' - Die Perfusiologie', 0, 0, 'C');

            // Output
            $filename = 'Artikel_' . $submission_id . '.pdf';
            $pdf->Output('D', $filename);
        }

        /**
         * Helper to safely convert UTF-8 to ISO-8859-1 for FPDF
         */
        private function utf8_decode_safe($string) {
            if (!$string) return '';
            // Replace common German and special characters
            $replacements = [
                "\xC3\xA4" => 'ae',  // ä
                "\xC3\xB6" => 'oe',  // ö
                "\xC3\xBC" => 'ue',  // ü
                "\xC3\x84" => 'Ae',  // Ä
                "\xC3\x96" => 'Oe',  // Ö
                "\xC3\x9C" => 'Ue',  // Ü
                "\xC3\x9F" => 'ss',  // ß
                "\xC3\xA9" => 'e',   // é
                "\xC3\xA8" => 'e',   // è
                "\xC3\xAA" => 'e',   // ê
                "\xC3\xA0" => 'a',   // à
                "\xC3\xA2" => 'a',   // â
                "\xC3\xB4" => 'o',   // ô
                "\xC3\xAE" => 'i',   // î
                "\xC3\xBB" => 'u',   // û
                "\xC3\xA7" => 'c',   // ç
                "\xE2\x82\xAC" => 'EUR',  // €
                "\xE2\x80\x93" => '-',    // –
                "\xE2\x80\x94" => '-',    // —
                "\xE2\x80\x9C" => '"',    // "
                "\xE2\x80\x9D" => '"',    // "
                "\xE2\x80\x98" => "'",    // '
                "\xE2\x80\x99" => "'",    // '
                "\xE2\x80\xA6" => '...',  // …
            ];
            $string = str_replace(array_keys($replacements), array_values($replacements), $string);
            $result = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $string);
            return $result !== false ? $result : $string;
        }

        private function define_constants() {
            if (!defined('DGPTM_ARTIKEL_PATH')) {
                define('DGPTM_ARTIKEL_PATH', plugin_dir_path(__FILE__));
            }
            if (!defined('DGPTM_ARTIKEL_URL')) {
                define('DGPTM_ARTIKEL_URL', plugin_dir_url(__FILE__));
            }
        }

        /**
         * Register Custom Post Type for Article Submissions
         */
        public function register_post_type() {
            if (post_type_exists(self::POST_TYPE)) {
                return;
            }

            register_post_type(self::POST_TYPE, [
                'labels' => [
                    'name' => 'Artikel-Einreichungen',
                    'singular_name' => 'Artikel-Einreichung',
                    'add_new' => 'Neue Einreichung',
                    'add_new_item' => 'Neue Einreichung hinzufügen',
                    'edit_item' => 'Einreichung bearbeiten',
                    'view_item' => 'Einreichung ansehen',
                    'all_items' => 'Alle Einreichungen',
                    'search_items' => 'Einreichungen suchen',
                    'not_found' => 'Keine Einreichungen gefunden'
                ],
                'public' => false,
                'show_ui' => true,
                'show_in_menu' => false, // We add custom menu
                'capability_type' => 'post',
                'hierarchical' => false,
                'supports' => ['title', 'editor', 'author', 'custom-fields'],
                'has_archive' => false,
                'rewrite' => false,
                'show_in_rest' => false
            ]);
        }

        /**
         * Register ACF Fields for Article Submissions
         */
        public function register_acf_fields() {
            if (!function_exists('acf_add_local_field_group')) {
                return;
            }

            acf_add_local_field_group([
                'key' => 'group_artikel_einreichung',
                'title' => 'Artikel-Einreichung Details',
                'fields' => [
                    // Basis-Informationen
                    [
                        'key' => 'field_artikel_status',
                        'label' => 'Status',
                        'name' => 'artikel_status',
                        'type' => 'select',
                        'choices' => [
                            self::STATUS_SUBMITTED => 'Eingereicht',
                            self::STATUS_FORMAL_CHECK => 'Formale Prüfung',
                            self::STATUS_UNDER_REVIEW => 'Im Review',
                            self::STATUS_REVISION_REQUIRED => 'Revision erforderlich',
                            self::STATUS_REVISION_SUBMITTED => 'Revision eingereicht',
                            self::STATUS_ACCEPTED => 'Angenommen',
                            self::STATUS_REJECTED => 'Abgelehnt',
                            self::STATUS_PUBLISHED => 'Veröffentlicht'
                        ],
                        'default_value' => self::STATUS_SUBMITTED
                    ],
                    [
                        'key' => 'field_artikel_publikationsart',
                        'label' => 'Publikationsart',
                        'name' => 'publikationsart',
                        'type' => 'select',
                        'choices' => self::PUBLIKATIONSARTEN,
                        'required' => 1
                    ],
                    [
                        'key' => 'field_artikel_unterueberschrift',
                        'label' => 'Untertitel',
                        'name' => 'unterueberschrift',
                        'type' => 'text'
                    ],
                    // Autoren
                    [
                        'key' => 'field_artikel_hauptautor',
                        'label' => 'Korrespondenzautor',
                        'name' => 'hauptautorin',
                        'type' => 'text',
                        'required' => 1
                    ],
                    [
                        'key' => 'field_artikel_hauptautor_email',
                        'label' => 'E-Mail Korrespondenzautor',
                        'name' => 'hauptautor_email',
                        'type' => 'email',
                        'required' => 1
                    ],
                    [
                        'key' => 'field_artikel_hauptautor_institution',
                        'label' => 'Institution Korrespondenzautor',
                        'name' => 'hauptautor_institution',
                        'type' => 'text'
                    ],
                    [
                        'key' => 'field_artikel_hauptautor_orcid',
                        'label' => 'ORCID Korrespondenzautor',
                        'name' => 'hauptautor_orcid',
                        'type' => 'text',
                        'instructions' => 'Format: 0000-0000-0000-0000 (https://orcid.org)',
                        'placeholder' => '0000-0000-0000-0000'
                    ],
                    [
                        'key' => 'field_artikel_koautoren',
                        'label' => 'Ko-Autoren',
                        'name' => 'autoren',
                        'type' => 'textarea',
                        'instructions' => 'Ein Autor pro Zeile: Name, Institution, ORCID (optional)'
                    ],
                    // Abstracts
                    [
                        'key' => 'field_artikel_abstract_deutsch',
                        'label' => 'Abstract (Deutsch)',
                        'name' => 'abstract-deutsch',
                        'type' => 'textarea',
                        'rows' => 6,
                        'required' => 1
                    ],
                    [
                        'key' => 'field_artikel_abstract_englisch',
                        'label' => 'Abstract (English)',
                        'name' => 'abstract',
                        'type' => 'textarea',
                        'rows' => 6
                    ],
                    // Keywords
                    [
                        'key' => 'field_artikel_keywords_deutsch',
                        'label' => 'Schlüsselwörter (Deutsch)',
                        'name' => 'keywords-deutsch',
                        'type' => 'text',
                        'instructions' => 'Kommagetrennt, max. 6'
                    ],
                    [
                        'key' => 'field_artikel_keywords_englisch',
                        'label' => 'Keywords (English)',
                        'name' => 'keywords-englisch',
                        'type' => 'text',
                        'instructions' => 'Comma-separated, max. 6'
                    ],
                    // Highlights
                    [
                        'key' => 'field_artikel_highlights',
                        'label' => 'Highlights',
                        'name' => 'highlights',
                        'type' => 'textarea',
                        'rows' => 4,
                        'required' => 1,
                        'instructions' => 'Three key findings/contributions of your work (in English). One highlight per line, numbered 1-3.'
                    ],
                    // Dateien
                    [
                        'key' => 'field_artikel_manuskript',
                        'label' => 'Manuskript (PDF/Word)',
                        'name' => 'manuskript',
                        'type' => 'file',
                        'return_format' => 'array',
                        'mime_types' => 'pdf,doc,docx',
                        'required' => 1
                    ],
                    [
                        'key' => 'field_artikel_abbildungen',
                        'label' => 'Abbildungen',
                        'name' => 'abbildungen',
                        'type' => 'gallery',
                        'return_format' => 'array',
                        'mime_types' => 'jpg,jpeg,png,tiff,eps'
                    ],
                    [
                        'key' => 'field_artikel_tabellen',
                        'label' => 'Tabellen',
                        'name' => 'tabellen',
                        'type' => 'file',
                        'return_format' => 'array',
                        'mime_types' => 'xlsx,xls,doc,docx'
                    ],
                    [
                        'key' => 'field_artikel_supplement',
                        'label' => 'Supplementary Material',
                        'name' => 'supplement_material',
                        'type' => 'file',
                        'return_format' => 'array'
                    ],
                    // Literatur
                    [
                        'key' => 'field_artikel_literatur',
                        'label' => 'Literaturverzeichnis',
                        'name' => 'literatur',
                        'type' => 'textarea',
                        'rows' => 10
                    ],
                    // Interessenkonflikte
                    [
                        'key' => 'field_artikel_coi',
                        'label' => 'Interessenkonflikte',
                        'name' => 'interessenkonflikte',
                        'type' => 'textarea',
                        'instructions' => 'Bitte alle potenziellen Interessenkonflikte angeben oder "Keine" eintragen'
                    ],
                    [
                        'key' => 'field_artikel_funding',
                        'label' => 'Förderung/Finanzierung',
                        'name' => 'funding',
                        'type' => 'textarea'
                    ],
                    [
                        'key' => 'field_artikel_ethik',
                        'label' => 'Ethikvotum',
                        'name' => 'ethikvotum',
                        'type' => 'text',
                        'instructions' => 'Aktenzeichen des Ethikvotums (falls zutreffend)'
                    ],
                    // Review-Felder
                    [
                        'key' => 'field_artikel_reviewer_1',
                        'label' => 'Reviewer 1',
                        'name' => 'reviewer_1',
                        'type' => 'user',
                        'allow_null' => 1
                    ],
                    [
                        'key' => 'field_artikel_reviewer_1_status',
                        'label' => 'Reviewer 1 Status',
                        'name' => 'reviewer_1_status',
                        'type' => 'select',
                        'choices' => [
                            '' => 'Nicht zugewiesen',
                            'pending' => 'Ausstehend',
                            'accepted' => 'Angenommen',
                            'declined' => 'Abgelehnt',
                            'completed' => 'Abgeschlossen'
                        ]
                    ],
                    [
                        'key' => 'field_artikel_reviewer_1_comment',
                        'label' => 'Reviewer 1 Gutachten',
                        'name' => 'reviewer_1_comment',
                        'type' => 'textarea',
                        'rows' => 10
                    ],
                    [
                        'key' => 'field_artikel_reviewer_1_recommendation',
                        'label' => 'Reviewer 1 Empfehlung',
                        'name' => 'reviewer_1_recommendation',
                        'type' => 'select',
                        'choices' => [
                            '' => 'Keine',
                            'accept' => 'Annehmen',
                            'minor_revision' => 'Kleinere Überarbeitung',
                            'major_revision' => 'Größere Überarbeitung',
                            'reject' => 'Ablehnen'
                        ]
                    ],
                    [
                        'key' => 'field_artikel_reviewer_2',
                        'label' => 'Reviewer 2',
                        'name' => 'reviewer_2',
                        'type' => 'user',
                        'allow_null' => 1
                    ],
                    [
                        'key' => 'field_artikel_reviewer_2_status',
                        'label' => 'Reviewer 2 Status',
                        'name' => 'reviewer_2_status',
                        'type' => 'select',
                        'choices' => [
                            '' => 'Nicht zugewiesen',
                            'pending' => 'Ausstehend',
                            'accepted' => 'Angenommen',
                            'declined' => 'Abgelehnt',
                            'completed' => 'Abgeschlossen'
                        ]
                    ],
                    [
                        'key' => 'field_artikel_reviewer_2_comment',
                        'label' => 'Reviewer 2 Gutachten',
                        'name' => 'reviewer_2_comment',
                        'type' => 'textarea',
                        'rows' => 10
                    ],
                    [
                        'key' => 'field_artikel_reviewer_2_recommendation',
                        'label' => 'Reviewer 2 Empfehlung',
                        'name' => 'reviewer_2_recommendation',
                        'type' => 'select',
                        'choices' => [
                            '' => 'Keine',
                            'accept' => 'Annehmen',
                            'minor_revision' => 'Kleinere Überarbeitung',
                            'major_revision' => 'Größere Überarbeitung',
                            'reject' => 'Ablehnen'
                        ]
                    ],
                    // Editor-Notizen
                    [
                        'key' => 'field_artikel_editor_notes',
                        'label' => 'Editor-Notizen (intern)',
                        'name' => 'editor_notes',
                        'type' => 'textarea',
                        'rows' => 5
                    ],
                    [
                        'key' => 'field_artikel_decision_letter',
                        'label' => 'Decision Letter',
                        'name' => 'decision_letter',
                        'type' => 'textarea',
                        'rows' => 10,
                        'instructions' => 'Offizielles Schreiben an den Autor'
                    ],
                    // Revision
                    [
                        'key' => 'field_artikel_revision_manuskript',
                        'label' => 'Revidiertes Manuskript',
                        'name' => 'revision_manuskript',
                        'type' => 'file',
                        'return_format' => 'array',
                        'mime_types' => 'pdf,doc,docx'
                    ],
                    [
                        'key' => 'field_artikel_revision_response',
                        'label' => 'Response to Reviewers',
                        'name' => 'revision_response',
                        'type' => 'textarea',
                        'rows' => 10
                    ],
                    // Timestamps
                    [
                        'key' => 'field_artikel_submitted_at',
                        'label' => 'Eingereicht am',
                        'name' => 'submitted_at',
                        'type' => 'date_time_picker',
                        'display_format' => 'd.m.Y H:i',
                        'return_format' => 'Y-m-d H:i:s'
                    ],
                    [
                        'key' => 'field_artikel_decision_at',
                        'label' => 'Entscheidung am',
                        'name' => 'decision_at',
                        'type' => 'date_time_picker',
                        'display_format' => 'd.m.Y H:i',
                        'return_format' => 'Y-m-d H:i:s'
                    ],
                    // Verknüpfung zur Publikation
                    [
                        'key' => 'field_artikel_publikation_id',
                        'label' => 'Verknüpfte Publikation',
                        'name' => 'publikation_id',
                        'type' => 'post_object',
                        'post_type' => ['publikation'],
                        'allow_null' => 1
                    ],
                    // Einreichungs-ID
                    [
                        'key' => 'field_artikel_submission_id',
                        'label' => 'Einreichungs-ID',
                        'name' => 'submission_id',
                        'type' => 'text',
                        'readonly' => 1
                    ],
                    // Author Access Token (for non-logged in authors)
                    [
                        'key' => 'field_artikel_author_token',
                        'label' => 'Autoren-Token',
                        'name' => 'author_token',
                        'type' => 'text',
                        'readonly' => 1,
                        'instructions' => 'Automatisch generierter Token für Autoren ohne Login'
                    ],
                    // Communication Log
                    [
                        'key' => 'field_artikel_communication_log',
                        'label' => 'Kommunikations-Log',
                        'name' => 'communication_log',
                        'type' => 'repeater',
                        'sub_fields' => [
                            [
                                'key' => 'field_artikel_comm_timestamp',
                                'label' => 'Zeitstempel',
                                'name' => 'timestamp',
                                'type' => 'number'
                            ],
                            [
                                'key' => 'field_artikel_comm_user_id',
                                'label' => 'Benutzer-ID',
                                'name' => 'user_id',
                                'type' => 'number'
                            ],
                            [
                                'key' => 'field_artikel_comm_user_name',
                                'label' => 'Benutzer-Name',
                                'name' => 'user_name',
                                'type' => 'text'
                            ],
                            [
                                'key' => 'field_artikel_comm_type',
                                'label' => 'Typ',
                                'name' => 'type',
                                'type' => 'text'
                            ],
                            [
                                'key' => 'field_artikel_comm_recipient',
                                'label' => 'Empfänger',
                                'name' => 'recipient',
                                'type' => 'text'
                            ],
                            [
                                'key' => 'field_artikel_comm_subject',
                                'label' => 'Betreff',
                                'name' => 'subject',
                                'type' => 'text'
                            ],
                            [
                                'key' => 'field_artikel_comm_body',
                                'label' => 'Inhalt',
                                'name' => 'body',
                                'type' => 'textarea'
                            ]
                        ]
                    ]
                ],
                'location' => [
                    [
                        [
                            'param' => 'post_type',
                            'operator' => '==',
                            'value' => self::POST_TYPE
                        ]
                    ]
                ],
                'position' => 'normal',
                'style' => 'default',
                'label_placement' => 'top'
            ]);
        }

        /**
         * Register Shortcodes
         */
        public function register_shortcodes() {
            add_shortcode('artikel_einreichung', [$this, 'render_submission_form']);
            add_shortcode('artikel_dashboard', [$this, 'render_author_dashboard']);
            add_shortcode('artikel_review', [$this, 'render_reviewer_dashboard']);
            add_shortcode('artikel_redaktion', [$this, 'render_redaktion_dashboard']);
            add_shortcode('artikel_editor_dashboard', [$this, 'render_editor_dashboard']);
        }

        /**
         * Add Admin Menus
         */
        public function add_admin_menus() {
            // Main menu for Editor in Chief
            add_menu_page(
                'Artikel-Einreichungen',
                'Perfusiologie',
                'edit_posts',
                'dgptm-artikel',
                [$this, 'render_admin_dashboard'],
                'dashicons-media-document',
                30
            );

            add_submenu_page(
                'dgptm-artikel',
                'Dashboard',
                'Dashboard',
                'edit_posts',
                'dgptm-artikel',
                [$this, 'render_admin_dashboard']
            );

            add_submenu_page(
                'dgptm-artikel',
                'Alle Einreichungen',
                'Alle Einreichungen',
                'edit_posts',
                'dgptm-artikel-list',
                [$this, 'render_admin_list']
            );

            add_submenu_page(
                'dgptm-artikel',
                'Reviewer-Verwaltung',
                'Reviewer-Liste',
                'manage_options',
                'dgptm-artikel-reviewers',
                [$this, 'render_reviewer_management']
            );

            add_submenu_page(
                'dgptm-artikel',
                'Einstellungen',
                'Einstellungen',
                'manage_options',
                'dgptm-artikel-settings',
                [$this, 'render_settings_page']
            );
        }

        /**
         * Enqueue Admin Assets
         */
        public function enqueue_admin_assets($hook) {
            if (strpos($hook, 'dgptm-artikel') === false) {
                return;
            }

            wp_enqueue_style(
                'dgptm-artikel-admin',
                DGPTM_ARTIKEL_URL . 'assets/css/admin.css',
                [],
                '1.0.0'
            );

            wp_enqueue_script(
                'dgptm-artikel-admin',
                DGPTM_ARTIKEL_URL . 'assets/js/admin.js',
                ['jquery'],
                '1.0.0',
                true
            );

            wp_localize_script('dgptm-artikel-admin', 'dgptmArtikel', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce(self::NONCE_ACTION)
            ]);
        }

        /**
         * Enqueue Frontend Assets
         */
        public function enqueue_frontend_assets() {
            global $post;
            if (!$post) return;

            // Only load on pages with our shortcodes
            if (has_shortcode($post->post_content, 'artikel_einreichung') ||
                has_shortcode($post->post_content, 'artikel_dashboard') ||
                has_shortcode($post->post_content, 'artikel_review') ||
                has_shortcode($post->post_content, 'artikel_redaktion') ||
                has_shortcode($post->post_content, 'artikel_editor_dashboard')) {

                wp_enqueue_style(
                    'dgptm-artikel-frontend',
                    DGPTM_ARTIKEL_URL . 'assets/css/frontend.css',
                    [],
                    '1.0.0'
                );

                wp_enqueue_script(
                    'dgptm-artikel-frontend',
                    DGPTM_ARTIKEL_URL . 'assets/js/frontend.js',
                    ['jquery'],
                    '1.0.0',
                    true
                );

                wp_localize_script('dgptm-artikel-frontend', 'dgptmArtikel', [
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce(self::NONCE_ACTION),
                    'maxFileSize' => 20 * 1024 * 1024, // 20MB
                    'allowedTypes' => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'tiff', 'xlsx', 'xls']
                ]);
            }
        }

        /**
         * Check if user is Editor in Chief
         */
        public function is_editor_in_chief($user_id = null) {
            if (!$user_id) {
                $user_id = get_current_user_id();
            }
            if (!$user_id) return false;

            // Check ACF user field
            $is_eic = get_field('editor_in_chief', 'user_' . $user_id);
            return (bool) $is_eic;
        }

        /**
         * Check if user is Redaktion
         */
        public function is_redaktion($user_id = null) {
            if (!$user_id) {
                $user_id = get_current_user_id();
            }
            if (!$user_id) return false;

            $is_red = get_field('redaktion_perfusiologie', 'user_' . $user_id);
            return (bool) $is_red;
        }

        /**
         * Check if user is a Reviewer for a specific article
         */
        public function is_reviewer_for_article($article_id, $user_id = null) {
            if (!$user_id) {
                $user_id = get_current_user_id();
            }
            if (!$user_id) return false;

            $reviewer_1 = get_field('reviewer_1', $article_id);
            $reviewer_2 = get_field('reviewer_2', $article_id);

            $r1_id = is_object($reviewer_1) ? $reviewer_1->ID : (is_array($reviewer_1) ? ($reviewer_1['ID'] ?? 0) : intval($reviewer_1));
            $r2_id = is_object($reviewer_2) ? $reviewer_2->ID : (is_array($reviewer_2) ? ($reviewer_2['ID'] ?? 0) : intval($reviewer_2));

            return ($r1_id == $user_id) || ($r2_id == $user_id);
        }

        /**
         * Get reviewer number for user on article (1 or 2)
         */
        public function get_reviewer_number($article_id, $user_id = null) {
            if (!$user_id) {
                $user_id = get_current_user_id();
            }

            $reviewer_1 = get_field('reviewer_1', $article_id);
            $reviewer_2 = get_field('reviewer_2', $article_id);

            $r1_id = is_object($reviewer_1) ? $reviewer_1->ID : (is_array($reviewer_1) ? ($reviewer_1['ID'] ?? 0) : intval($reviewer_1));
            $r2_id = is_object($reviewer_2) ? $reviewer_2->ID : (is_array($reviewer_2) ? ($reviewer_2['ID'] ?? 0) : intval($reviewer_2));

            if ($r1_id == $user_id) return 1;
            if ($r2_id == $user_id) return 2;
            return 0;
        }

        /**
         * Generate Submission ID
         */
        private function generate_submission_id() {
            $year = date('Y');
            $count = wp_count_posts(self::POST_TYPE);
            $total = $count->publish + $count->draft + $count->pending + 1;
            return sprintf('PERF-%s-%04d', $year, $total);
        }

        /**
         * Generate Author Token
         * Creates a unique, secure token for author access without login
         */
        private function generate_author_token() {
            return bin2hex(random_bytes(32)); // 64-character hex string
        }

        /**
         * Validate Author Token
         * Returns article ID if valid, false otherwise
         */
        public function validate_author_token($token) {
            if (empty($token) || strlen($token) !== 64) {
                return false;
            }

            $articles = get_posts([
                'post_type' => self::POST_TYPE,
                'meta_key' => 'author_token',
                'meta_value' => sanitize_text_field($token),
                'posts_per_page' => 1,
                'post_status' => 'publish'
            ]);

            if (!empty($articles)) {
                return $articles[0]->ID;
            }

            return false;
        }

        /**
         * Get Author Dashboard URL with token
         */
        public function get_author_dashboard_url($article_id) {
            $token = get_field('author_token', $article_id);
            $dashboard_page = get_option('dgptm_artikel_dashboard_page', '');

            if (!$dashboard_page) {
                // Try to find page with shortcode
                $pages = get_posts([
                    'post_type' => 'page',
                    's' => '[artikel_dashboard',
                    'posts_per_page' => 1
                ]);
                if (!empty($pages)) {
                    $dashboard_page = get_permalink($pages[0]->ID);
                } else {
                    $dashboard_page = home_url('/');
                }
            }

            return add_query_arg('autor_token', $token, $dashboard_page);
        }

        /**
         * Get status label
         */
        public function get_status_label($status) {
            $labels = [
                self::STATUS_SUBMITTED => 'Eingereicht',
                self::STATUS_FORMAL_CHECK => 'Formale Prüfung',
                self::STATUS_UNDER_REVIEW => 'Im Review',
                self::STATUS_REVISION_REQUIRED => 'Revision erforderlich',
                self::STATUS_REVISION_SUBMITTED => 'Revision eingereicht',
                self::STATUS_ACCEPTED => 'Angenommen',
                self::STATUS_REJECTED => 'Abgelehnt',
                self::STATUS_PUBLISHED => 'Veröffentlicht'
            ];
            return $labels[$status] ?? $status;
        }

        /**
         * Get status color class
         */
        public function get_status_class($status) {
            $classes = [
                self::STATUS_SUBMITTED => 'status-blue',
                self::STATUS_FORMAL_CHECK => 'status-cyan',
                self::STATUS_UNDER_REVIEW => 'status-orange',
                self::STATUS_REVISION_REQUIRED => 'status-yellow',
                self::STATUS_REVISION_SUBMITTED => 'status-blue',
                self::STATUS_ACCEPTED => 'status-green',
                self::STATUS_REJECTED => 'status-red',
                self::STATUS_PUBLISHED => 'status-purple'
            ];
            return $classes[$status] ?? 'status-gray';
        }

        /**
         * Send notification email
         */
        private function send_notification($to, $subject, $message, $article_id = null) {
            $headers = ['Content-Type: text/html; charset=UTF-8'];
            $from_name = get_bloginfo('name');
            $from_email = get_option('admin_email');
            $headers[] = "From: {$from_name} <{$from_email}>";

            // Wrap in template
            $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family: Arial, sans-serif; line-height: 1.6;">';
            $html .= '<div style="max-width: 600px; margin: 0 auto; padding: 20px;">';
            $html .= '<h2 style="color: #1a365d;">Die Perfusiologie - Artikelverwaltung</h2>';
            $html .= '<hr style="border: 1px solid #e2e8f0;">';
            $html .= wpautop($message);
            if ($article_id) {
                $submission_id = get_field('submission_id', $article_id);
                $html .= '<p style="color: #718096; font-size: 12px;">Referenz: ' . esc_html($submission_id) . '</p>';
            }
            $html .= '</div></body></html>';

            wp_mail($to, $subject, $html, $headers);
        }

        // =====================================================
        // AJAX HANDLERS
        // =====================================================

        /**
         * AJAX: Submit new article
         * Works for both logged-in and non-logged-in users
         */
        public function ajax_submit_artikel() {
            check_ajax_referer(self::NONCE_ACTION, 'nonce');

            // User ID is 0 for non-logged in users
            $user_id = get_current_user_id();

            // Validate required fields
            $required = ['titel', 'publikationsart', 'hauptautor', 'hauptautor_email', 'abstract_deutsch', 'highlights'];
            foreach ($required as $field) {
                if (empty($_POST[$field])) {
                    wp_send_json_error(['message' => 'Bitte füllen Sie alle Pflichtfelder aus.']);
                }
            }

            // Validate ORCID format if provided
            if (!empty($_POST['hauptautor_orcid'])) {
                $orcid = sanitize_text_field($_POST['hauptautor_orcid']);
                if (!preg_match('/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/', $orcid)) {
                    wp_send_json_error(['message' => 'Bitte geben Sie eine gültige ORCID-ID ein (Format: 0000-0000-0000-0000).']);
                }
            }

            // Check for manuscript upload
            if (empty($_FILES['manuskript']) || $_FILES['manuskript']['error'] !== UPLOAD_ERR_OK) {
                wp_send_json_error(['message' => 'Bitte laden Sie das Manuskript hoch.']);
            }

            // Create post
            $post_id = wp_insert_post([
                'post_title' => sanitize_text_field($_POST['titel']),
                'post_content' => '',
                'post_type' => self::POST_TYPE,
                'post_status' => 'publish',
                'post_author' => $user_id
            ]);

            if (is_wp_error($post_id)) {
                wp_send_json_error(['message' => 'Fehler beim Erstellen der Einreichung.']);
            }

            // Generate submission ID
            $submission_id = $this->generate_submission_id();
            update_field('submission_id', $submission_id, $post_id);

            // Save ACF fields
            update_field('artikel_status', self::STATUS_SUBMITTED, $post_id);
            update_field('publikationsart', sanitize_text_field($_POST['publikationsart']), $post_id);
            update_field('unterueberschrift', sanitize_text_field($_POST['unterueberschrift'] ?? ''), $post_id);
            update_field('hauptautorin', sanitize_text_field($_POST['hauptautor']), $post_id);
            update_field('hauptautor_email', sanitize_email($_POST['hauptautor_email']), $post_id);
            update_field('hauptautor_institution', sanitize_text_field($_POST['hauptautor_institution'] ?? ''), $post_id);
            update_field('hauptautor_orcid', sanitize_text_field($_POST['hauptautor_orcid'] ?? ''), $post_id);
            update_field('autoren', sanitize_textarea_field($_POST['koautoren'] ?? ''), $post_id);
            update_field('abstract-deutsch', sanitize_textarea_field($_POST['abstract_deutsch']), $post_id);
            update_field('abstract', sanitize_textarea_field($_POST['abstract_englisch'] ?? ''), $post_id);
            update_field('keywords-deutsch', sanitize_text_field($_POST['keywords_deutsch'] ?? ''), $post_id);
            update_field('keywords-englisch', sanitize_text_field($_POST['keywords_englisch'] ?? ''), $post_id);
            update_field('highlights', sanitize_textarea_field($_POST['highlights']), $post_id);
            update_field('literatur', sanitize_textarea_field($_POST['literatur'] ?? ''), $post_id);
            update_field('interessenkonflikte', sanitize_textarea_field($_POST['interessenkonflikte'] ?? ''), $post_id);
            update_field('funding', sanitize_textarea_field($_POST['funding'] ?? ''), $post_id);
            update_field('ethikvotum', sanitize_text_field($_POST['ethikvotum'] ?? ''), $post_id);
            update_field('submitted_at', current_time('Y-m-d H:i:s'), $post_id);

            // Handle file uploads
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');

            // Manuscript
            $manuskript_id = media_handle_upload('manuskript', $post_id);
            if (!is_wp_error($manuskript_id)) {
                update_field('manuskript', $manuskript_id, $post_id);
            }

            // Figures (gallery)
            if (!empty($_FILES['abbildungen'])) {
                $gallery_ids = [];
                $files = $_FILES['abbildungen'];
                for ($i = 0; $i < count($files['name']); $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $_FILES['upload_file'] = [
                            'name' => $files['name'][$i],
                            'type' => $files['type'][$i],
                            'tmp_name' => $files['tmp_name'][$i],
                            'error' => $files['error'][$i],
                            'size' => $files['size'][$i]
                        ];
                        $attach_id = media_handle_upload('upload_file', $post_id);
                        if (!is_wp_error($attach_id)) {
                            $gallery_ids[] = $attach_id;
                        }
                    }
                }
                if (!empty($gallery_ids)) {
                    update_field('abbildungen', $gallery_ids, $post_id);
                }
            }

            // Tables
            if (!empty($_FILES['tabellen']) && $_FILES['tabellen']['error'] === UPLOAD_ERR_OK) {
                $tabellen_id = media_handle_upload('tabellen', $post_id);
                if (!is_wp_error($tabellen_id)) {
                    update_field('tabellen', $tabellen_id, $post_id);
                }
            }

            // Supplement
            if (!empty($_FILES['supplement']) && $_FILES['supplement']['error'] === UPLOAD_ERR_OK) {
                $supplement_id = media_handle_upload('supplement', $post_id);
                if (!is_wp_error($supplement_id)) {
                    update_field('supplement_material', $supplement_id, $post_id);
                }
            }

            // Generate author token for access without login
            $author_token = $this->generate_author_token();
            update_field('author_token', $author_token, $post_id);

            // Build dashboard URL with token
            $dashboard_url = $this->get_author_dashboard_url($post_id);

            // Send confirmation email to author with dashboard link
            $author_email = sanitize_email($_POST['hauptautor_email']);
            $this->send_notification(
                $author_email,
                'Artikel eingereicht: ' . $submission_id,
                "Sehr geehrte/r " . sanitize_text_field($_POST['hauptautor']) . ",\n\n" .
                "Vielen Dank für die Einreichung Ihres Artikels bei Die Perfusiologie.\n\n" .
                "Titel: " . sanitize_text_field($_POST['titel']) . "\n" .
                "Einreichungs-ID: " . $submission_id . "\n\n" .
                "Über folgenden Link können Sie jederzeit den Status Ihrer Einreichung einsehen:\n" .
                "<a href=\"" . esc_url($dashboard_url) . "\">" . esc_html($dashboard_url) . "</a>\n\n" .
                "<strong>Bitte bewahren Sie diesen Link sicher auf!</strong> Er ist Ihr persönlicher Zugang zu Ihrer Einreichung.\n\n" .
                "Wir werden Ihren Artikel prüfen und uns in Kürze bei Ihnen melden.\n\n" .
                "Mit freundlichen Grüßen,\nDie Redaktion",
                $post_id
            );

            // Notify Editor in Chief
            $editors = get_users([
                'meta_key' => 'editor_in_chief',
                'meta_value' => '1'
            ]);
            foreach ($editors as $editor) {
                $this->send_notification(
                    $editor->user_email,
                    'Neue Artikel-Einreichung: ' . $submission_id,
                    "Eine neue Artikel-Einreichung ist eingegangen.\n\n" .
                    "Titel: " . sanitize_text_field($_POST['titel']) . "\n" .
                    "Autor: " . sanitize_text_field($_POST['hauptautor']) . "\n" .
                    "Einreichungs-ID: " . $submission_id . "\n\n" .
                    "Bitte prüfen Sie die Einreichung und weisen Sie Reviewer zu.",
                    $post_id
                );
            }

            wp_send_json_success([
                'message' => 'Vielen Dank! Ihr Artikel wurde erfolgreich eingereicht.',
                'submission_id' => $submission_id
            ]);
        }

        /**
         * AJAX: Assign reviewer
         */
        public function ajax_assign_reviewer() {
            check_ajax_referer(self::NONCE_ACTION, 'nonce');

            if (!$this->is_editor_in_chief()) {
                wp_send_json_error(['message' => 'Keine Berechtigung.']);
            }

            $article_id = intval($_POST['article_id'] ?? 0);
            $reviewer_id = intval($_POST['reviewer_id'] ?? 0);
            $slot = intval($_POST['slot'] ?? 1); // 1 or 2

            if (!$article_id || !$reviewer_id || !in_array($slot, [1, 2])) {
                wp_send_json_error(['message' => 'Ungültige Parameter.']);
            }

            // Update reviewer field
            update_field('reviewer_' . $slot, $reviewer_id, $article_id);
            update_field('reviewer_' . $slot . '_status', 'pending', $article_id);

            // Update article status if first reviewer assigned
            $current_status = get_field('artikel_status', $article_id);
            if ($current_status === self::STATUS_SUBMITTED) {
                update_field('artikel_status', self::STATUS_UNDER_REVIEW, $article_id);
            }

            // Send notification to reviewer
            $reviewer = get_user_by('ID', $reviewer_id);
            if ($reviewer) {
                $article_title = get_the_title($article_id);
                $submission_id = get_field('submission_id', $article_id);

                $this->send_notification(
                    $reviewer->user_email,
                    'Review-Anfrage: ' . $submission_id,
                    "Sehr geehrte/r " . $reviewer->display_name . ",\n\n" .
                    "Sie wurden gebeten, einen Artikel für Die Perfusiologie zu begutachten.\n\n" .
                    "Referenz: " . $submission_id . "\n\n" .
                    "Bitte melden Sie sich auf der Website an, um den Artikel zu prüfen und Ihre Bewertung abzugeben.\n\n" .
                    "Vielen Dank für Ihre Unterstützung!\n\n" .
                    "Mit freundlichen Grüßen,\nDie Redaktion",
                    $article_id
                );
            }

            wp_send_json_success(['message' => 'Reviewer zugewiesen.']);
        }

        /**
         * AJAX: Submit review
         */
        public function ajax_submit_review() {
            check_ajax_referer(self::NONCE_ACTION, 'nonce');

            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'Bitte melden Sie sich an.']);
            }

            $article_id = intval($_POST['article_id'] ?? 0);
            $user_id = get_current_user_id();

            if (!$this->is_reviewer_for_article($article_id, $user_id)) {
                wp_send_json_error(['message' => 'Sie sind nicht als Reviewer für diesen Artikel eingetragen.']);
            }

            $slot = $this->get_reviewer_number($article_id, $user_id);
            if (!$slot) {
                wp_send_json_error(['message' => 'Reviewer-Slot nicht gefunden.']);
            }

            $comment = sanitize_textarea_field($_POST['comment'] ?? '');
            $recommendation = sanitize_text_field($_POST['recommendation'] ?? '');

            if (empty($comment) || empty($recommendation)) {
                wp_send_json_error(['message' => 'Bitte füllen Sie alle Felder aus.']);
            }

            // Save review
            update_field('reviewer_' . $slot . '_comment', $comment, $article_id);
            update_field('reviewer_' . $slot . '_recommendation', $recommendation, $article_id);
            update_field('reviewer_' . $slot . '_status', 'completed', $article_id);

            // Notify Editor in Chief
            $editors = get_users([
                'meta_key' => 'editor_in_chief',
                'meta_value' => '1'
            ]);
            $submission_id = get_field('submission_id', $article_id);
            foreach ($editors as $editor) {
                $this->send_notification(
                    $editor->user_email,
                    'Review abgeschlossen: ' . $submission_id,
                    "Ein Review wurde abgeschlossen.\n\n" .
                    "Einreichungs-ID: " . $submission_id . "\n" .
                    "Reviewer: " . $slot . "\n" .
                    "Empfehlung: " . $this->get_recommendation_label($recommendation) . "\n\n" .
                    "Bitte prüfen Sie die Einreichung und treffen Sie eine Entscheidung.",
                    $article_id
                );
            }

            wp_send_json_success(['message' => 'Vielen Dank! Ihr Review wurde gespeichert.']);
        }

        /**
         * Get recommendation label
         */
        private function get_recommendation_label($rec) {
            $labels = [
                'accept' => 'Annehmen',
                'minor_revision' => 'Kleinere Überarbeitung',
                'major_revision' => 'Größere Überarbeitung',
                'reject' => 'Ablehnen'
            ];
            return $labels[$rec] ?? $rec;
        }

        /**
         * AJAX: Editor decision
         */
        public function ajax_editor_decision() {
            check_ajax_referer(self::NONCE_ACTION, 'nonce');

            if (!$this->is_editor_in_chief()) {
                wp_send_json_error(['message' => 'Keine Berechtigung.']);
            }

            $article_id = intval($_POST['article_id'] ?? 0);
            $decision = sanitize_text_field($_POST['decision'] ?? '');
            $letter = sanitize_textarea_field($_POST['decision_letter'] ?? '');

            if (!$article_id || !$decision) {
                wp_send_json_error(['message' => 'Ungültige Parameter.']);
            }

            // Map decision to status
            $status_map = [
                'accept' => self::STATUS_ACCEPTED,
                'revision' => self::STATUS_REVISION_REQUIRED,
                'reject' => self::STATUS_REJECTED
            ];

            $new_status = $status_map[$decision] ?? '';
            if (!$new_status) {
                wp_send_json_error(['message' => 'Ungültige Entscheidung.']);
            }

            // Update article
            update_field('artikel_status', $new_status, $article_id);
            update_field('decision_letter', $letter, $article_id);
            update_field('decision_at', current_time('Y-m-d H:i:s'), $article_id);

            // Notify author
            $author_email = get_field('hauptautor_email', $article_id);
            $author_name = get_field('hauptautorin', $article_id);
            $submission_id = get_field('submission_id', $article_id);
            $article_title = get_the_title($article_id);

            $subject_map = [
                self::STATUS_ACCEPTED => 'Ihr Artikel wurde angenommen',
                self::STATUS_REVISION_REQUIRED => 'Überarbeitung erforderlich',
                self::STATUS_REJECTED => 'Entscheidung zu Ihrer Einreichung'
            ];

            // Get dashboard URL for author
            $dashboard_url = $this->get_author_dashboard_url($article_id);

            $revision_text = '';
            if ($new_status === self::STATUS_REVISION_REQUIRED) {
                $revision_text = "Bitte laden Sie die überarbeitete Version über Ihr Autoren-Dashboard hoch:\n" .
                    "<a href=\"" . esc_url($dashboard_url) . "\">" . esc_html($dashboard_url) . "</a>\n\n";
            }

            $this->send_notification(
                $author_email,
                $subject_map[$new_status] . ': ' . $submission_id,
                "Sehr geehrte/r " . $author_name . ",\n\n" .
                "zu Ihrer Einreichung \"" . $article_title . "\" (" . $submission_id . ") liegt nun eine Entscheidung vor:\n\n" .
                "Status: " . $this->get_status_label($new_status) . "\n\n" .
                ($letter ? "Begründung:\n" . $letter . "\n\n" : "") .
                $revision_text .
                "Ihr persönlicher Zugangslink:\n<a href=\"" . esc_url($dashboard_url) . "\">" . esc_html($dashboard_url) . "</a>\n\n" .
                "Mit freundlichen Grüßen,\nDie Redaktion",
                $article_id
            );

            wp_send_json_success([
                'message' => 'Entscheidung gespeichert und Autor benachrichtigt.',
                'new_status' => $new_status
            ]);
        }

        /**
         * AJAX: Submit revision
         */
        public function ajax_submit_revision() {
            check_ajax_referer(self::NONCE_ACTION, 'nonce');

            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'Bitte melden Sie sich an.']);
            }

            $article_id = intval($_POST['article_id'] ?? 0);
            $user_id = get_current_user_id();

            // Check if user is author
            $article = get_post($article_id);
            if (!$article || $article->post_author != $user_id) {
                wp_send_json_error(['message' => 'Keine Berechtigung.']);
            }

            // Check status
            $status = get_field('artikel_status', $article_id);
            if ($status !== self::STATUS_REVISION_REQUIRED) {
                wp_send_json_error(['message' => 'Für diesen Artikel ist keine Revision angefordert.']);
            }

            // Check file upload
            if (empty($_FILES['revision_manuskript']) || $_FILES['revision_manuskript']['error'] !== UPLOAD_ERR_OK) {
                wp_send_json_error(['message' => 'Bitte laden Sie das revidierte Manuskript hoch.']);
            }

            $response = sanitize_textarea_field($_POST['revision_response'] ?? '');
            if (empty($response)) {
                wp_send_json_error(['message' => 'Bitte geben Sie eine Antwort auf die Reviewer-Kommentare an.']);
            }

            // Handle file upload
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');

            $revision_id = media_handle_upload('revision_manuskript', $article_id);
            if (is_wp_error($revision_id)) {
                wp_send_json_error(['message' => 'Fehler beim Hochladen der Datei.']);
            }

            // Update fields
            update_field('revision_manuskript', $revision_id, $article_id);
            update_field('revision_response', $response, $article_id);
            update_field('artikel_status', self::STATUS_REVISION_SUBMITTED, $article_id);

            // Notify Editor in Chief
            $editors = get_users([
                'meta_key' => 'editor_in_chief',
                'meta_value' => '1'
            ]);
            $submission_id = get_field('submission_id', $article_id);
            foreach ($editors as $editor) {
                $this->send_notification(
                    $editor->user_email,
                    'Revision eingereicht: ' . $submission_id,
                    "Eine Revision wurde eingereicht.\n\n" .
                    "Einreichungs-ID: " . $submission_id . "\n\n" .
                    "Bitte prüfen Sie die überarbeitete Version.",
                    $article_id
                );
            }

            wp_send_json_success(['message' => 'Ihre Revision wurde erfolgreich eingereicht.']);
        }

        /**
         * AJAX: Submit revision via token (for non-logged in users)
         */
        public function ajax_submit_revision_token() {
            check_ajax_referer(self::NONCE_ACTION, 'nonce');

            $token = sanitize_text_field($_POST['author_token'] ?? '');
            $article_id = $this->validate_author_token($token);

            if (!$article_id) {
                wp_send_json_error(['message' => 'Ungültiger oder abgelaufener Zugangslink.']);
            }

            // Check status
            $status = get_field('artikel_status', $article_id);
            if ($status !== self::STATUS_REVISION_REQUIRED) {
                wp_send_json_error(['message' => 'Für diesen Artikel ist keine Revision angefordert.']);
            }

            // Check file upload
            if (empty($_FILES['revision_manuskript']) || $_FILES['revision_manuskript']['error'] !== UPLOAD_ERR_OK) {
                wp_send_json_error(['message' => 'Bitte laden Sie das revidierte Manuskript hoch.']);
            }

            $response = sanitize_textarea_field($_POST['revision_response'] ?? '');
            if (empty($response)) {
                wp_send_json_error(['message' => 'Bitte geben Sie eine Antwort auf die Reviewer-Kommentare an.']);
            }

            // Handle file upload
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');

            $revision_id = media_handle_upload('revision_manuskript', $article_id);
            if (is_wp_error($revision_id)) {
                wp_send_json_error(['message' => 'Fehler beim Hochladen der Datei.']);
            }

            // Update fields
            update_field('revision_manuskript', $revision_id, $article_id);
            update_field('revision_response', $response, $article_id);
            update_field('artikel_status', self::STATUS_REVISION_SUBMITTED, $article_id);

            // Notify Editor in Chief
            $editors = get_users([
                'meta_key' => 'editor_in_chief',
                'meta_value' => '1'
            ]);
            $submission_id = get_field('submission_id', $article_id);
            foreach ($editors as $editor) {
                $this->send_notification(
                    $editor->user_email,
                    'Revision eingereicht: ' . $submission_id,
                    "Eine Revision wurde eingereicht.\n\n" .
                    "Einreichungs-ID: " . $submission_id . "\n\n" .
                    "Bitte prüfen Sie die überarbeitete Version.",
                    $article_id
                );
            }

            wp_send_json_success(['message' => 'Ihre Revision wurde erfolgreich eingereicht.']);
        }

        /**
         * AJAX: Update article status
         */
        public function ajax_update_status() {
            check_ajax_referer(self::NONCE_ACTION, 'nonce');

            if (!$this->is_editor_in_chief()) {
                wp_send_json_error(['message' => 'Keine Berechtigung.']);
            }

            $article_id = intval($_POST['article_id'] ?? 0);
            $new_status = sanitize_text_field($_POST['status'] ?? '');

            if (!$article_id || !$new_status) {
                wp_send_json_error(['message' => 'Ungültige Parameter.']);
            }

            update_field('artikel_status', $new_status, $article_id);

            wp_send_json_success(['message' => 'Status aktualisiert.']);
        }

        /**
         * AJAX: Save reviewer list
         */
        public function ajax_save_reviewer_list() {
            check_ajax_referer(self::NONCE_ACTION, 'nonce');

            if (!$this->is_editor_in_chief() && !current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung.']);
            }

            $reviewer_ids = isset($_POST['reviewer_ids']) ? array_map('intval', (array)$_POST['reviewer_ids']) : [];

            update_option(self::OPT_REVIEWERS, $reviewer_ids);

            wp_send_json_success(['message' => 'Reviewer-Liste gespeichert.']);
        }

        /**
         * AJAX: Save editor notes
         */
        public function ajax_save_editor_notes() {
            check_ajax_referer(self::NONCE_ACTION, 'nonce');

            if (!$this->is_editor_in_chief()) {
                wp_send_json_error(['message' => 'Keine Berechtigung.']);
            }

            $article_id = intval($_POST['article_id'] ?? 0);
            $notes = sanitize_textarea_field($_POST['notes'] ?? '');

            if (!$article_id) {
                wp_send_json_error(['message' => 'Ungültige Artikel-ID.']);
            }

            update_field('editor_notes', $notes, $article_id);

            wp_send_json_success(['message' => 'Notizen gespeichert.']);
        }

        /**
         * AJAX: Search users for reviewer selection
         */
        public function ajax_search_users() {
            check_ajax_referer(self::NONCE_ACTION, 'nonce');

            if (!$this->is_editor_in_chief() && !current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung.']);
            }

            $search = sanitize_text_field($_POST['search'] ?? '');

            if (strlen($search) < 2) {
                wp_send_json_success(['users' => []]);
            }

            $users = get_users([
                'search' => '*' . $search . '*',
                'search_columns' => ['display_name', 'user_email', 'user_login'],
                'number' => 20,
                'orderby' => 'display_name'
            ]);

            $results = [];
            foreach ($users as $user) {
                $results[] = [
                    'id' => $user->ID,
                    'name' => $user->display_name,
                    'email' => $user->user_email
                ];
            }

            wp_send_json_success(['users' => $results]);
        }

        /**
         * AJAX: Add a reviewer to the pool
         */
        public function ajax_add_reviewer() {
            check_ajax_referer(self::NONCE_ACTION, 'nonce');

            if (!$this->is_editor_in_chief() && !current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung.']);
            }

            $user_id = intval($_POST['user_id'] ?? 0);

            if (!$user_id) {
                wp_send_json_error(['message' => 'Ungültige Benutzer-ID.']);
            }

            $reviewer_ids = get_option(self::OPT_REVIEWERS, []);
            if (!in_array($user_id, $reviewer_ids)) {
                $reviewer_ids[] = $user_id;
                update_option(self::OPT_REVIEWERS, $reviewer_ids);
            }

            wp_send_json_success(['message' => 'Reviewer hinzugefügt.']);
        }

        /**
         * AJAX: Remove a reviewer from the pool
         */
        public function ajax_remove_reviewer() {
            check_ajax_referer(self::NONCE_ACTION, 'nonce');

            if (!$this->is_editor_in_chief() && !current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung.']);
            }

            $user_id = intval($_POST['user_id'] ?? 0);

            if (!$user_id) {
                wp_send_json_error(['message' => 'Ungültige Benutzer-ID.']);
            }

            $reviewer_ids = get_option(self::OPT_REVIEWERS, []);
            $reviewer_ids = array_filter($reviewer_ids, function($id) use ($user_id) {
                return $id !== $user_id;
            });
            $reviewer_ids = array_values($reviewer_ids); // Re-index
            update_option(self::OPT_REVIEWERS, $reviewer_ids);

            wp_send_json_success(['message' => 'Reviewer entfernt.']);
        }

        /**
         * AJAX: Save reviewer notes (expertise, availability)
         */
        public function ajax_save_reviewer_notes() {
            check_ajax_referer(self::NONCE_ACTION, 'nonce');

            if (!$this->is_editor_in_chief() && !current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung.']);
            }

            $user_id = intval($_POST['user_id'] ?? 0);
            $expertise = sanitize_text_field($_POST['expertise'] ?? '');
            $availability = sanitize_text_field($_POST['availability'] ?? '');
            $notes = sanitize_textarea_field($_POST['notes'] ?? '');

            if (!$user_id) {
                wp_send_json_error(['message' => 'Ungültige Benutzer-ID.']);
            }

            // Get existing notes
            $all_notes = get_option(self::OPT_REVIEWER_NOTES, []);

            // Update notes for this reviewer
            $all_notes[$user_id] = [
                'expertise' => $expertise,
                'availability' => $availability,
                'notes' => $notes,
                'updated_at' => current_time('timestamp'),
                'updated_by' => get_current_user_id()
            ];

            update_option(self::OPT_REVIEWER_NOTES, $all_notes);

            wp_send_json_success(['message' => 'Notizen gespeichert.']);
        }

        /**
         * Get reviewer notes
         */
        public function get_reviewer_notes($user_id) {
            $all_notes = get_option(self::OPT_REVIEWER_NOTES, []);
            return $all_notes[$user_id] ?? [
                'expertise' => '',
                'availability' => '',
                'notes' => ''
            ];
        }

        /**
         * AJAX: Preview email before sending
         */
        public function ajax_preview_email() {
            check_ajax_referer(self::NONCE_ACTION, 'nonce');

            if (!$this->is_editor_in_chief() && !$this->is_redaktion() && !current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung.']);
            }

            $article_id = intval($_POST['article_id'] ?? 0);
            $email_type = sanitize_text_field($_POST['email_type'] ?? '');
            $recipient_type = sanitize_text_field($_POST['recipient_type'] ?? 'author'); // author or reviewer
            $reviewer_id = intval($_POST['reviewer_id'] ?? 0);

            if (!$article_id) {
                wp_send_json_error(['message' => 'Ungültige Artikel-ID.']);
            }

            $article = get_post($article_id);
            if (!$article) {
                wp_send_json_error(['message' => 'Artikel nicht gefunden.']);
            }

            // Get article data
            $submission_id = get_field('submission_id', $article_id);
            $author_name = get_field('hauptautorin', $article_id);
            $author_email = get_field('hauptautor_email', $article_id);
            $article_title = $article->post_title;

            // Determine recipient
            if ($recipient_type === 'reviewer' && $reviewer_id) {
                $reviewer = get_user_by('ID', $reviewer_id);
                $recipient_email = $reviewer ? $reviewer->user_email : '';
                $recipient_name = $reviewer ? $reviewer->display_name : '';
            } else {
                $recipient_email = $author_email;
                $recipient_name = $author_name;
            }

            // Get email template
            $settings = get_option(self::OPT_SETTINGS, []);
            $template_key = 'email_' . $email_type;
            $template = $settings[$template_key] ?? [];

            $subject = $template['subject'] ?? 'Die Perfusiologie - Ihre Einreichung';
            $body = $template['body'] ?? '';

            // Replace placeholders
            $placeholders = [
                '{author_name}' => $author_name,
                '{recipient_name}' => $recipient_name,
                '{submission_id}' => $submission_id,
                '{article_title}' => $article_title,
                '{site_name}' => get_bloginfo('name'),
                '{site_url}' => get_site_url()
            ];

            $subject = str_replace(array_keys($placeholders), array_values($placeholders), $subject);
            $body = str_replace(array_keys($placeholders), array_values($placeholders), $body);

            wp_send_json_success([
                'recipient_email' => $recipient_email,
                'recipient_name' => $recipient_name,
                'subject' => $subject,
                'body' => $body,
                'article_title' => $article_title,
                'submission_id' => $submission_id
            ]);
        }

        /**
         * AJAX: Send custom email (with preview/editing)
         */
        public function ajax_send_custom_email() {
            check_ajax_referer(self::NONCE_ACTION, 'nonce');

            if (!$this->is_editor_in_chief() && !$this->is_redaktion() && !current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung.']);
            }

            $article_id = intval($_POST['article_id'] ?? 0);
            $recipient_email = sanitize_email($_POST['recipient_email'] ?? '');
            $subject = sanitize_text_field($_POST['subject'] ?? '');
            $body = wp_kses_post($_POST['body'] ?? '');
            $email_type = sanitize_text_field($_POST['email_type'] ?? 'custom');

            if (!$article_id || !$recipient_email || !$subject || !$body) {
                wp_send_json_error(['message' => 'Bitte füllen Sie alle Felder aus.']);
            }

            // Send email
            $headers = ['Content-Type: text/html; charset=UTF-8'];
            $sent = wp_mail($recipient_email, $subject, nl2br($body), $headers);

            if (!$sent) {
                wp_send_json_error(['message' => 'E-Mail konnte nicht gesendet werden.']);
            }

            // Log the communication
            $this->log_communication($article_id, $email_type, $recipient_email, $subject, $body);

            wp_send_json_success(['message' => 'E-Mail erfolgreich gesendet.']);
        }

        /**
         * Log communication for an article
         */
        public function log_communication($article_id, $type, $recipient, $subject, $body) {
            $log = get_field('communication_log', $article_id) ?: [];

            $log[] = [
                'timestamp' => current_time('timestamp'),
                'user_id' => get_current_user_id(),
                'user_name' => wp_get_current_user()->display_name,
                'type' => $type,
                'recipient' => $recipient,
                'subject' => $subject,
                'body' => $body
            ];

            update_field('communication_log', $log, $article_id);
        }

        /**
         * Get communication log for an article
         */
        public function get_communication_log($article_id) {
            return get_field('communication_log', $article_id) ?: [];
        }

        /**
         * AJAX: Get all text snippets
         */
        public function ajax_get_text_snippets() {
            check_ajax_referer(self::NONCE_ACTION, 'nonce');

            if (!$this->is_editor_in_chief() && !$this->is_redaktion() && !current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung.']);
            }

            $snippets = get_option(self::OPT_TEXT_SNIPPETS, $this->get_default_snippets());
            wp_send_json_success(['snippets' => $snippets]);
        }

        /**
         * AJAX: Save a text snippet
         */
        public function ajax_save_text_snippet() {
            check_ajax_referer(self::NONCE_ACTION, 'nonce');

            if (!$this->is_editor_in_chief() && !current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung.']);
            }

            $snippet_id = sanitize_text_field($_POST['snippet_id'] ?? '');
            $category = sanitize_text_field($_POST['category'] ?? 'general');
            $title = sanitize_text_field($_POST['title'] ?? '');
            $content = wp_kses_post($_POST['content'] ?? '');

            if (!$title || !$content) {
                wp_send_json_error(['message' => 'Titel und Inhalt sind erforderlich.']);
            }

            $snippets = get_option(self::OPT_TEXT_SNIPPETS, []);

            // Generate ID if new
            if (!$snippet_id) {
                $snippet_id = 'snippet_' . uniqid();
            }

            $snippets[$snippet_id] = [
                'id' => $snippet_id,
                'category' => $category,
                'title' => $title,
                'content' => $content,
                'updated_at' => current_time('timestamp'),
                'updated_by' => get_current_user_id()
            ];

            update_option(self::OPT_TEXT_SNIPPETS, $snippets);

            wp_send_json_success([
                'message' => 'Textbaustein gespeichert.',
                'snippet' => $snippets[$snippet_id]
            ]);
        }

        /**
         * AJAX: Delete a text snippet
         */
        public function ajax_delete_text_snippet() {
            check_ajax_referer(self::NONCE_ACTION, 'nonce');

            if (!$this->is_editor_in_chief() && !current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung.']);
            }

            $snippet_id = sanitize_text_field($_POST['snippet_id'] ?? '');

            if (!$snippet_id) {
                wp_send_json_error(['message' => 'Ungültige Snippet-ID.']);
            }

            $snippets = get_option(self::OPT_TEXT_SNIPPETS, []);

            if (isset($snippets[$snippet_id])) {
                unset($snippets[$snippet_id]);
                update_option(self::OPT_TEXT_SNIPPETS, $snippets);
            }

            wp_send_json_success(['message' => 'Textbaustein gelöscht.']);
        }

        /**
         * Get default text snippets
         */
        private function get_default_snippets() {
            return [
                'formal_incomplete' => [
                    'id' => 'formal_incomplete',
                    'category' => 'formal',
                    'title' => 'Unvollständige Angaben',
                    'content' => "Leider fehlen in Ihrer Einreichung noch einige Angaben, die für die weitere Bearbeitung erforderlich sind:\n\n- [Fehlende Angabe 1]\n- [Fehlende Angabe 2]\n\nBitte ergänzen Sie diese Informationen und reichen Sie das Manuskript erneut ein."
                ],
                'formal_format' => [
                    'id' => 'formal_format',
                    'category' => 'formal',
                    'title' => 'Formatierung nicht korrekt',
                    'content' => "Die Formatierung Ihres Manuskripts entspricht nicht unseren Autorenrichtlinien:\n\n- [Formatierungsfehler 1]\n- [Formatierungsfehler 2]\n\nBitte passen Sie das Manuskript entsprechend an."
                ],
                'review_positive' => [
                    'id' => 'review_positive',
                    'category' => 'review',
                    'title' => 'Positives Feedback',
                    'content' => "Vielen Dank für Ihre interessante Einreichung. Das Thema ist relevant für unsere Leserschaft und die Darstellung ist klar strukturiert."
                ],
                'review_revision_minor' => [
                    'id' => 'review_revision_minor',
                    'category' => 'review',
                    'title' => 'Kleinere Revisionen nötig',
                    'content' => "Nach Durchsicht der Gutachten empfehlen wir kleinere Überarbeitungen:\n\n[Zusammenfassung der Reviewer-Kommentare]\n\nBitte reichen Sie die überarbeitete Version innerhalb von 4 Wochen ein."
                ],
                'review_revision_major' => [
                    'id' => 'review_revision_major',
                    'category' => 'review',
                    'title' => 'Größere Revisionen nötig',
                    'content' => "Die Gutachter empfehlen umfangreiche Überarbeitungen:\n\n[Zusammenfassung der Haupt-Kritikpunkte]\n\nWir bitten Sie, die Punkte sorgfältig zu adressieren und eine überarbeitete Version einzureichen."
                ],
                'acceptance' => [
                    'id' => 'acceptance',
                    'category' => 'decision',
                    'title' => 'Annahme',
                    'content' => "Wir freuen uns, Ihnen mitteilen zu können, dass Ihr Manuskript zur Veröffentlichung in Die Perfusiologie angenommen wurde.\n\nWir werden Sie zeitnah über die nächsten Schritte (Druckfahnen, Publikationsdatum) informieren."
                ],
                'rejection' => [
                    'id' => 'rejection',
                    'category' => 'decision',
                    'title' => 'Ablehnung',
                    'content' => "Nach sorgfältiger Prüfung müssen wir Ihnen leider mitteilen, dass wir Ihr Manuskript nicht zur Veröffentlichung annehmen können.\n\n[Begründung]\n\nWir danken Ihnen für das Einreichen Ihrer Arbeit und wünschen Ihnen viel Erfolg bei anderen Zeitschriften."
                ]
            ];
        }

        /**
         * Get text snippets
         */
        public function get_text_snippets() {
            return get_option(self::OPT_TEXT_SNIPPETS, $this->get_default_snippets());
        }

        /**
         * AJAX: Save article settings (from frontend editor dashboard)
         */
        public function ajax_save_artikel_settings() {
            check_ajax_referer(self::NONCE_ACTION, 'nonce');

            if (!$this->is_editor_in_chief() && !current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung.']);
            }

            // Email template types
            $email_types = [
                'email_submission',
                'email_reviewer_request',
                'email_revision_request',
                'email_accepted',
                'email_rejected',
                'email_status_update'
            ];

            $settings = [
                'email_notifications' => intval($_POST['email_notifications'] ?? 0),
                'notification_email' => sanitize_email($_POST['notification_email'] ?? ''),
                'review_instructions' => wp_kses_post($_POST['review_instructions'] ?? ''),
                'max_file_size' => intval($_POST['max_file_size'] ?? 20),
                // Master template
                'master_header' => wp_kses_post($_POST['master_header'] ?? ''),
                'master_footer' => wp_kses_post($_POST['master_footer'] ?? '')
            ];

            // Save each email template
            foreach ($email_types as $type) {
                $settings[$type] = [
                    'enabled' => intval($_POST[$type . '_enabled'] ?? 0),
                    'subject' => sanitize_text_field($_POST[$type . '_subject'] ?? ''),
                    'body' => wp_kses_post($_POST[$type . '_body'] ?? '')
                ];
            }

            update_option(self::OPT_SETTINGS, $settings);

            wp_send_json_success(['message' => 'Einstellungen gespeichert.']);
        }

        /**
         * Get available reviewers
         */
        public function get_reviewers() {
            $reviewer_ids = get_option(self::OPT_REVIEWERS, []);
            if (empty($reviewer_ids)) {
                return [];
            }

            $users = get_users([
                'include' => $reviewer_ids,
                'orderby' => 'display_name'
            ]);

            return $users;
        }

        // =====================================================
        // RENDER METHODS
        // =====================================================

        /**
         * Render submission form shortcode
         * Works for both logged-in and non-logged-in users
         */
        public function render_submission_form($atts) {
            // Allow submission without login
            ob_start();
            include DGPTM_ARTIKEL_PATH . 'templates/submission-form.php';
            return ob_get_clean();
        }

        /**
         * Render author dashboard shortcode
         * Supports both logged-in users and token-based access
         */
        public function render_author_dashboard($atts) {
            // Check for token access
            $token = isset($_GET['autor_token']) ? sanitize_text_field($_GET['autor_token']) : '';
            $token_article_id = $token ? $this->validate_author_token($token) : false;

            // If token is provided but invalid
            if ($token && !$token_article_id) {
                return '<div class="dgptm-artikel-notice notice-error">
                    <p>Ungültiger oder abgelaufener Zugangslink. Bitte überprüfen Sie den Link in Ihrer E-Mail.</p>
                </div>';
            }

            // If no token and not logged in
            if (!$token_article_id && !is_user_logged_in()) {
                return '<div class="dgptm-artikel-notice notice-warning">
                    <p>Bitte <a href="' . esc_url(wp_login_url(get_permalink())) . '">melden Sie sich an</a> oder verwenden Sie den Link aus Ihrer Bestätigungs-E-Mail.</p>
                </div>';
            }

            ob_start();
            // Pass token info to template
            $GLOBALS['dgptm_artikel_token'] = $token;
            $GLOBALS['dgptm_artikel_token_article_id'] = $token_article_id;
            include DGPTM_ARTIKEL_PATH . 'templates/author-dashboard.php';
            return ob_get_clean();
        }

        /**
         * Render reviewer dashboard shortcode
         */
        public function render_reviewer_dashboard($atts) {
            if (!is_user_logged_in()) {
                return '<div class="dgptm-artikel-notice notice-warning">
                    <p>Bitte <a href="' . esc_url(wp_login_url(get_permalink())) . '">melden Sie sich an</a>.</p>
                </div>';
            }

            ob_start();
            include DGPTM_ARTIKEL_PATH . 'templates/reviewer-dashboard.php';
            return ob_get_clean();
        }

        /**
         * Render redaktion dashboard shortcode
         */
        public function render_redaktion_dashboard($atts) {
            if (!is_user_logged_in()) {
                return '<div class="dgptm-artikel-notice notice-warning">
                    <p>Bitte <a href="' . esc_url(wp_login_url(get_permalink())) . '">melden Sie sich an</a>.</p>
                </div>';
            }

            if (!$this->is_redaktion() && !$this->is_editor_in_chief()) {
                return '<div class="dgptm-artikel-notice notice-error">
                    <p>Keine Berechtigung.</p>
                </div>';
            }

            ob_start();
            include DGPTM_ARTIKEL_PATH . 'templates/redaktion-dashboard.php';
            return ob_get_clean();
        }

        /**
         * Render editor dashboard shortcode (Frontend for Editor in Chief)
         */
        public function render_editor_dashboard($atts) {
            if (!is_user_logged_in()) {
                return '<div class="dgptm-artikel-notice notice-warning">
                    <p>Bitte <a href="' . esc_url(wp_login_url(get_permalink())) . '">melden Sie sich an</a>.</p>
                </div>';
            }

            if (!$this->is_editor_in_chief()) {
                return '<div class="dgptm-artikel-notice notice-error">
                    <p>Nur der Editor-in-Chief hat Zugang zu diesem Bereich.</p>
                </div>';
            }

            ob_start();
            include DGPTM_ARTIKEL_PATH . 'templates/editor-dashboard.php';
            return ob_get_clean();
        }

        /**
         * Render admin dashboard
         */
        public function render_admin_dashboard() {
            if (!$this->is_editor_in_chief() && !current_user_can('manage_options')) {
                echo '<div class="notice notice-error"><p>Keine Berechtigung.</p></div>';
                return;
            }
            include DGPTM_ARTIKEL_PATH . 'templates/admin/dashboard.php';
        }

        /**
         * Render admin list
         */
        public function render_admin_list() {
            if (!$this->is_editor_in_chief() && !$this->is_redaktion() && !current_user_can('manage_options')) {
                echo '<div class="notice notice-error"><p>Keine Berechtigung.</p></div>';
                return;
            }
            include DGPTM_ARTIKEL_PATH . 'templates/admin/list.php';
        }

        /**
         * Render reviewer management
         */
        public function render_reviewer_management() {
            if (!$this->is_editor_in_chief() && !current_user_can('manage_options')) {
                echo '<div class="notice notice-error"><p>Keine Berechtigung.</p></div>';
                return;
            }
            include DGPTM_ARTIKEL_PATH . 'templates/admin/reviewers.php';
        }

        /**
         * Render settings page
         */
        public function render_settings_page() {
            if (!current_user_can('manage_options')) {
                echo '<div class="notice notice-error"><p>Keine Berechtigung.</p></div>';
                return;
            }
            include DGPTM_ARTIKEL_PATH . 'templates/admin/settings.php';
        }

    } // End class

} // End if class_exists

// Initialize
if (!isset($GLOBALS['dgptm_artikel_einreichung_initialized'])) {
    $GLOBALS['dgptm_artikel_einreichung_initialized'] = true;
    DGPTM_Artikel_Einreichung::get_instance();
}
