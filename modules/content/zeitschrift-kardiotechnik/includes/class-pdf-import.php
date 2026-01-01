<?php
/**
 * PDF Import für komplette Zeitschriften-Ausgaben
 *
 * Importiert PDFs von Zeitschriften-Ausgaben, extrahiert:
 * - Titelseite als Bild
 * - Ausgaben-Metadaten (Jahr, Ausgabe, DOI)
 * - Einzelne Artikel mit Text, Bildern und als PDF
 *
 * @package DGPTM_Zeitschrift_Kardiotechnik
 * @since 1.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('ZK_PDF_Import')) {

    class ZK_PDF_Import {

        private static $instance = null;
        private $upload_dir;
        private $temp_dir;
        private $import_dir;

        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            $this->upload_dir = wp_upload_dir();
            $this->temp_dir = $this->upload_dir['basedir'] . '/zk-pdf-temp/';
            $this->import_dir = $this->upload_dir['basedir'] . '/zk-imports/';

            // Verzeichnisse erstellen
            if (!file_exists($this->temp_dir)) {
                wp_mkdir_p($this->temp_dir);
            }
            if (!file_exists($this->import_dir)) {
                wp_mkdir_p($this->import_dir);
            }

            // AJAX Handler registrieren
            add_action('wp_ajax_zk_upload_pdf', [$this, 'ajax_upload_pdf']);
            add_action('wp_ajax_zk_extract_issue', [$this, 'ajax_extract_issue']);
            add_action('wp_ajax_zk_ai_analyze_issue', [$this, 'ajax_ai_analyze_issue']);
            add_action('wp_ajax_zk_save_issue_import', [$this, 'ajax_save_issue_import']);
            add_action('wp_ajax_zk_discard_import', [$this, 'ajax_discard_import']);
            add_action('wp_ajax_zk_get_ai_settings', [$this, 'ajax_get_ai_settings']);
            add_action('wp_ajax_zk_save_ai_settings', [$this, 'ajax_save_ai_settings']);

            // Cleanup Cron
            add_action('zk_cleanup_temp_files', [$this, 'cleanup_temp_files']);
            if (!wp_next_scheduled('zk_cleanup_temp_files')) {
                wp_schedule_event(time(), 'daily', 'zk_cleanup_temp_files');
            }
        }

        /**
         * PDF hochladen
         */
        public function ajax_upload_pdf() {
            check_ajax_referer('zk_admin_nonce', 'nonce');

            if (!current_user_can('edit_posts')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
                wp_send_json_error(['message' => 'Kein PDF hochgeladen']);
            }

            $file = $_FILES['pdf'];

            // Nur PDFs erlauben
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);

            if ($mime !== 'application/pdf') {
                wp_send_json_error(['message' => 'Nur PDF-Dateien erlaubt']);
            }

            // Eindeutige Import-ID erstellen
            $import_id = 'import_' . uniqid();
            $import_path = $this->import_dir . $import_id . '/';
            wp_mkdir_p($import_path);

            // Datei speichern
            $filename = sanitize_file_name($file['name']);
            $filepath = $import_path . 'source.pdf';

            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                wp_send_json_error(['message' => 'Fehler beim Speichern']);
            }

            // Import-Status speichern
            $import_data = [
                'id' => $import_id,
                'filename' => $filename,
                'filepath' => $filepath,
                'size' => filesize($filepath),
                'status' => 'uploaded',
                'created' => time(),
            ];
            file_put_contents($import_path . 'import.json', json_encode($import_data, JSON_PRETTY_PRINT));

            wp_send_json_success([
                'import_id' => $import_id,
                'filename' => $filename,
                'size' => filesize($filepath),
            ]);
        }

        /**
         * Ausgabe extrahieren UND mit KI analysieren (kombinierter Schritt)
         */
        public function ajax_extract_issue() {
            check_ajax_referer('zk_admin_nonce', 'nonce');

            if (!current_user_can('edit_posts')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $import_id = sanitize_text_field($_POST['import_id'] ?? '');
            if (empty($import_id)) {
                wp_send_json_error(['message' => 'Ungültige Import-ID']);
            }

            $import_path = $this->import_dir . $import_id . '/';
            $import_file = $import_path . 'import.json';

            if (!file_exists($import_file)) {
                wp_send_json_error(['message' => 'Import nicht gefunden']);
            }

            $import_data = json_decode(file_get_contents($import_file), true);
            $pdf_path = $import_data['filepath'];

            // AI-Einstellungen laden und prüfen
            $settings = get_option('zk_ai_settings', []);
            $api_key = $settings['api_key'] ?? '';
            $provider = $settings['provider'] ?? 'anthropic';

            if (empty($api_key)) {
                wp_send_json_error([
                    'message' => 'Kein API-Key konfiguriert. Bitte zuerst die KI-Einstellungen konfigurieren.',
                    'need_config' => true
                ]);
            }

            // 1. Titelseite als Bild extrahieren
            $cover_path = $this->extract_cover_page($pdf_path, $import_path);

            // 2. Seitenanzahl ermitteln
            $page_count = $this->get_page_count($pdf_path);

            // 3. Volltext extrahieren
            $full_text = $this->extract_full_text($pdf_path);

            // 4. Alle Bilder extrahieren
            $images = $this->extract_all_images($pdf_path, $import_path);

            // Zwischenspeichern
            $import_data['cover_path'] = $cover_path;
            $import_data['page_count'] = $page_count;
            $import_data['full_text'] = $full_text;
            $import_data['images'] = $images;
            $import_data['char_count'] = strlen($full_text);

            // 5. KI-Analyse durchführen
            $analysis = $this->analyze_issue_with_ai(
                $full_text,
                $page_count,
                $api_key,
                $provider
            );

            if (is_wp_error($analysis)) {
                // Bei KI-Fehler trotzdem Extraktion zurückgeben
                $import_data['status'] = 'extraction_only';
                file_put_contents($import_file, json_encode($import_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                wp_send_json_error([
                    'message' => 'KI-Analyse fehlgeschlagen: ' . $analysis->get_error_message(),
                    'extraction_done' => true,
                    'cover_url' => $cover_path ? str_replace($this->upload_dir['basedir'], $this->upload_dir['baseurl'], $cover_path) : null,
                    'page_count' => $page_count,
                ]);
            }

            // 6. Einzelne Artikel-PDFs erstellen
            if (!empty($analysis['articles'])) {
                $analysis['articles'] = $this->create_article_pdfs(
                    $pdf_path,
                    $analysis['articles'],
                    $import_path
                );
            }

            // Import-Daten komplett aktualisieren
            $import_data['status'] = 'analyzed';
            $import_data['issue'] = $analysis['issue'] ?? [];
            $import_data['articles'] = $analysis['articles'] ?? [];

            file_put_contents($import_file, json_encode($import_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            wp_send_json_success([
                'import_id' => $import_id,
                'cover_url' => $cover_path ? str_replace($this->upload_dir['basedir'], $this->upload_dir['baseurl'], $cover_path) : null,
                'page_count' => $page_count,
                'char_count' => strlen($full_text),
                'image_count' => count($images),
                'issue' => $analysis['issue'],
                'articles' => $analysis['articles'],
            ]);
        }

        /**
         * KI-Analyse der Ausgabe (Legacy - leitet zu ajax_extract_issue weiter)
         */
        public function ajax_ai_analyze_issue() {
            // Für Rückwärtskompatibilität - ruft extract auf
            $this->ajax_extract_issue();
        }

        /**
         * Import speichern (Ausgabe + Artikel erstellen)
         */
        public function ajax_save_issue_import() {
            check_ajax_referer('zk_admin_nonce', 'nonce');

            if (!current_user_can('edit_posts')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $import_id = sanitize_text_field($_POST['import_id'] ?? '');
            $issue_data = isset($_POST['issue']) ? json_decode(wp_unslash($_POST['issue']), true) : [];
            $articles_data = isset($_POST['articles']) ? json_decode(wp_unslash($_POST['articles']), true) : [];

            if (empty($import_id)) {
                wp_send_json_error(['message' => 'Ungültige Import-ID']);
            }

            $import_path = $this->import_dir . $import_id . '/';
            $import_file = $import_path . 'import.json';

            if (!file_exists($import_file)) {
                wp_send_json_error(['message' => 'Import nicht gefunden']);
            }

            $import_data = json_decode(file_get_contents($import_file), true);

            // 1. Titelseite in Mediathek importieren
            $cover_attachment_id = null;
            if (!empty($import_data['cover_path']) && file_exists($import_data['cover_path'])) {
                $cover_attachment_id = $this->import_to_media_library(
                    $import_data['cover_path'],
                    'Titelseite ' . ($issue_data['jahr'] ?? '') . '/' . ($issue_data['ausgabe'] ?? '')
                );
            }

            // 2. Ausgabe (zeitschkardiotechnik) erstellen
            $issue_post_id = wp_insert_post([
                'post_type' => ZK_POST_TYPE,
                'post_title' => 'Kardiotechnik ' . ($issue_data['jahr'] ?? date('Y')) . '/' . ($issue_data['ausgabe'] ?? '1'),
                'post_status' => 'publish',
                'post_author' => get_current_user_id(),
            ]);

            if (is_wp_error($issue_post_id)) {
                wp_send_json_error(['message' => 'Fehler beim Erstellen der Ausgabe']);
            }

            // ACF-Felder für Ausgabe setzen
            update_field('jahr', $issue_data['jahr'] ?? date('Y'), $issue_post_id);
            update_field('ausgabe', $issue_data['ausgabe'] ?? '1', $issue_post_id);
            update_field('doi', $issue_data['doi'] ?? '', $issue_post_id);

            if ($cover_attachment_id) {
                update_field('titelseite', $cover_attachment_id, $issue_post_id);
            }

            // Verfügbar ab (heute)
            update_field('verfuegbar_ab', date('d/m/Y'), $issue_post_id);

            // 3. Artikel erstellen und verknüpfen
            $created_articles = [];
            $slot_mapping = [
                'Editorial' => 'editorial',
                'Journal Club' => 'journalclub',
                'Tutorial' => 'tutorial',
            ];

            $pub_counter = 1;

            foreach ($articles_data as $index => $article) {
                // Artikel (publikation) erstellen
                $article_post_id = wp_insert_post([
                    'post_type' => ZK_PUBLIKATION_TYPE,
                    'post_title' => $article['title'] ?? 'Artikel ' . ($index + 1),
                    'post_content' => $article['content'] ?? '',
                    'post_status' => 'publish',
                    'post_author' => get_current_user_id(),
                ]);

                if (is_wp_error($article_post_id)) {
                    continue;
                }

                // ACF-Felder für Artikel setzen
                $this->set_article_fields($article_post_id, $article);

                // Artikel-PDF importieren und verknüpfen
                if (!empty($article['pdf_path']) && file_exists($article['pdf_path'])) {
                    $pdf_attachment_id = $this->import_to_media_library(
                        $article['pdf_path'],
                        $article['title'] ?? 'Artikel'
                    );
                    if ($pdf_attachment_id) {
                        update_field('pdf-volltext', $pdf_attachment_id, $article_post_id);
                    }
                }

                // Bilder importieren
                if (!empty($article['images'])) {
                    foreach ($article['images'] as $img_index => $image_path) {
                        if (file_exists($image_path)) {
                            $this->import_to_media_library($image_path, 'Bild ' . ($img_index + 1), $article_post_id);
                        }
                    }
                }

                // Artikel mit Ausgabe verknüpfen
                $pub_type = $article['publication_type'] ?? '';
                if (isset($slot_mapping[$pub_type])) {
                    update_field($slot_mapping[$pub_type], $article_post_id, $issue_post_id);
                } else {
                    // Nummerierte Slots (pub1-pub6)
                    if ($pub_counter <= 6) {
                        update_field('pub' . $pub_counter, $article_post_id, $issue_post_id);
                        $pub_counter++;
                    }
                }

                $created_articles[] = [
                    'id' => $article_post_id,
                    'title' => $article['title'],
                    'edit_url' => admin_url('post.php?post=' . $article_post_id . '&action=edit'),
                ];
            }

            // Import-Verzeichnis aufräumen
            $this->delete_directory($import_path);

            wp_send_json_success([
                'issue_id' => $issue_post_id,
                'issue_edit_url' => admin_url('post.php?post=' . $issue_post_id . '&action=edit'),
                'articles' => $created_articles,
                'message' => 'Ausgabe mit ' . count($created_articles) . ' Artikeln importiert',
            ]);
        }

        /**
         * Import verwerfen
         */
        public function ajax_discard_import() {
            check_ajax_referer('zk_admin_nonce', 'nonce');

            if (!current_user_can('edit_posts')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $import_id = sanitize_text_field($_POST['import_id'] ?? '');
            if (empty($import_id)) {
                wp_send_json_error(['message' => 'Ungültige Import-ID']);
            }

            $import_path = $this->import_dir . $import_id . '/';

            if (is_dir($import_path)) {
                $this->delete_directory($import_path);
            }

            wp_send_json_success(['message' => 'Import verworfen']);
        }

        /**
         * Titelseite als Bild extrahieren
         */
        private function extract_cover_page($pdf_path, $output_dir) {
            $cover_path = $output_dir . 'cover.jpg';

            // Methode 1: pdftoppm (Poppler)
            if ($this->command_exists('pdftoppm')) {
                $command = sprintf(
                    'pdftoppm -jpeg -f 1 -l 1 -r 150 %s %s',
                    escapeshellarg($pdf_path),
                    escapeshellarg($output_dir . 'cover')
                );
                exec($command, $output, $return_var);

                // pdftoppm fügt -1 an
                $generated = $output_dir . 'cover-1.jpg';
                if (file_exists($generated)) {
                    rename($generated, $cover_path);
                    return $cover_path;
                }
            }

            // Methode 2: ImageMagick convert
            if ($this->command_exists('convert')) {
                $command = sprintf(
                    'convert -density 150 %s[0] -quality 90 %s',
                    escapeshellarg($pdf_path),
                    escapeshellarg($cover_path)
                );
                exec($command, $output, $return_var);

                if ($return_var === 0 && file_exists($cover_path)) {
                    return $cover_path;
                }
            }

            // Methode 3: Ghostscript
            if ($this->command_exists('gs')) {
                $command = sprintf(
                    'gs -dNOPAUSE -dBATCH -sDEVICE=jpeg -dFirstPage=1 -dLastPage=1 -r150 -sOutputFile=%s %s',
                    escapeshellarg($cover_path),
                    escapeshellarg($pdf_path)
                );
                exec($command, $output, $return_var);

                if ($return_var === 0 && file_exists($cover_path)) {
                    return $cover_path;
                }
            }

            return null;
        }

        /**
         * Seitenanzahl ermitteln
         */
        private function get_page_count($pdf_path) {
            // Methode 1: pdfinfo
            if ($this->command_exists('pdfinfo')) {
                $command = sprintf('pdfinfo %s 2>&1', escapeshellarg($pdf_path));
                exec($command, $output, $return_var);

                foreach ($output as $line) {
                    if (preg_match('/^Pages:\s*(\d+)/', $line, $m)) {
                        return intval($m[1]);
                    }
                }
            }

            // Fallback: Aus PDF-Header lesen
            $content = file_get_contents($pdf_path, false, null, 0, 50000);
            if (preg_match('/\/N\s+(\d+)/', $content, $m)) {
                return intval($m[1]);
            }

            return 1;
        }

        /**
         * Volltext extrahieren
         */
        private function extract_full_text($pdf_path) {
            $text = '';

            // Methode 1: pdftotext mit Layout
            if ($this->command_exists('pdftotext')) {
                $output_file = $this->temp_dir . uniqid('txt_') . '.txt';
                $command = sprintf(
                    'pdftotext -layout %s %s 2>&1',
                    escapeshellarg($pdf_path),
                    escapeshellarg($output_file)
                );
                exec($command, $output, $return_var);

                if ($return_var === 0 && file_exists($output_file)) {
                    $text = file_get_contents($output_file);
                    unlink($output_file);
                }
            }

            // Fallback: PHP-basierte Extraktion
            if (empty($text)) {
                $text = $this->extract_text_php($pdf_path);
            }

            return $this->clean_text($text);
        }

        /**
         * PHP-basierte Textextraktion
         */
        private function extract_text_php($pdf_path) {
            $content = file_get_contents($pdf_path);
            $text = '';

            if (preg_match_all('/stream\s*(.*?)\s*endstream/s', $content, $matches)) {
                foreach ($matches[1] as $stream) {
                    $decoded = @gzuncompress($stream);
                    if ($decoded === false) {
                        $decoded = @gzinflate($stream);
                    }
                    if ($decoded !== false) {
                        if (preg_match_all('/\[(.*?)\]\s*TJ/s', $decoded, $textMatches)) {
                            foreach ($textMatches[1] as $textPart) {
                                preg_match_all('/\((.*?)\)/s', $textPart, $strings);
                                $text .= implode('', $strings[1]) . ' ';
                            }
                        }
                        if (preg_match_all('/\((.*?)\)\s*Tj/s', $decoded, $textMatches)) {
                            $text .= implode(' ', $textMatches[1]) . ' ';
                        }
                    }
                }
            }

            return $text;
        }

        /**
         * Text bereinigen
         */
        private function clean_text($text) {
            $text = preg_replace('/[ \t]+/', ' ', $text);
            $text = preg_replace('/\n{3,}/', "\n\n", $text);
            $text = preg_replace('/\f/', "\n\n--- Seitenumbruch ---\n\n", $text);
            return trim($text);
        }

        /**
         * Alle Bilder aus PDF extrahieren
         */
        private function extract_all_images($pdf_path, $output_dir) {
            $images = [];
            $image_dir = $output_dir . 'images/';
            wp_mkdir_p($image_dir);

            if ($this->command_exists('pdfimages')) {
                $command = sprintf(
                    'pdfimages -all %s %s 2>&1',
                    escapeshellarg($pdf_path),
                    escapeshellarg($image_dir . 'img')
                );
                exec($command, $output, $return_var);

                if ($return_var === 0) {
                    $files = glob($image_dir . 'img*');
                    foreach ($files as $file) {
                        // Nur Bilder mit vernünftiger Größe (> 5KB)
                        if (filesize($file) > 5000) {
                            $images[] = [
                                'path' => $file,
                                'url' => str_replace($this->upload_dir['basedir'], $this->upload_dir['baseurl'], $file),
                                'filename' => basename($file),
                                'size' => filesize($file),
                            ];
                        }
                    }
                }
            }

            return $images;
        }

        /**
         * KI-Analyse der Ausgabe
         */
        private function analyze_issue_with_ai($text, $page_count, $api_key, $provider = 'anthropic') {
            // Text kürzen wenn zu lang
            $max_chars = 120000;
            if (strlen($text) > $max_chars) {
                $text = substr($text, 0, $max_chars) . "\n\n[Text gekürzt...]";
            }

            $prompt = $this->build_issue_analysis_prompt($text, $page_count);

            if ($provider === 'anthropic') {
                return $this->call_anthropic_api($prompt, $api_key);
            } elseif ($provider === 'openai') {
                return $this->call_openai_api($prompt, $api_key);
            }

            return new WP_Error('invalid_provider', 'Ungültiger KI-Provider');
        }

        /**
         * Prompt für Ausgaben-Analyse
         */
        private function build_issue_analysis_prompt($text, $page_count) {
            return <<<PROMPT
Du bist ein Experte für wissenschaftliche Fachzeitschriften im Bereich Kardiotechnik/Medizin.

Analysiere den folgenden extrahierten Text einer Zeitschriftenausgabe und identifiziere:
1. Die Metadaten der Ausgabe (Jahr, Ausgabennummer, DOI)
2. Alle einzelnen Artikel mit ihren Grenzen (Seitenzahlen)

Die Ausgabe hat insgesamt {$page_count} Seiten.

Antworte NUR mit einem validen JSON-Objekt (ohne Markdown-Codeblöcke):
{
    "issue": {
        "jahr": "2024",
        "ausgabe": "1",
        "doi": "10.1234/example",
        "title": "Kardiotechnik 2024/1"
    },
    "articles": [
        {
            "title": "Titel des Artikels",
            "subtitle": "Untertitel falls vorhanden",
            "authors": "Autor 1, Autor 2, Autor 3",
            "main_author": "Erstautor",
            "publication_type": "Fachartikel|Editorial|Journal Club|Tutorial|Fallbericht|Übersichtsarbeit",
            "start_page": 1,
            "end_page": 5,
            "abstract_de": "Deutsche Zusammenfassung",
            "abstract_en": "English abstract if available",
            "keywords_de": "Schlüsselwort1, Schlüsselwort2",
            "keywords_en": "keyword1, keyword2",
            "content": "Der Haupttext des Artikels in HTML-Format mit <h2>, <h3>, <p>, <ul>, <strong>, <em>. Strukturiere den Text sinnvoll mit Überschriften für Abschnitte wie Einleitung, Methoden, Ergebnisse, Diskussion.",
            "references": "Literaturverzeichnis als HTML-Liste"
        }
    ]
}

Wichtige Hinweise:
- Identifiziere ALLE Artikel in der Ausgabe
- Editorials, Journal Clubs und Tutorials sind spezielle Publikationstypen
- Der "content" soll den vollständigen Artikeltext enthalten, HTML-formatiert
- Seitenzahlen helfen beim späteren PDF-Split
- Bei Unsicherheit über Seitengrenzen, schätze basierend auf Textlänge
- Entferne Kopf-/Fußzeilen und Seitenzahlen aus dem Content
- Falls ein Feld nicht gefunden wird, verwende einen leeren String

--- Extrahierter Text der Ausgabe ---

{$text}

--- Ende des Textes ---

Antworte NUR mit dem JSON-Objekt.
PROMPT;
        }

        /**
         * Anthropic Claude API
         */
        private function call_anthropic_api($prompt, $api_key) {
            $settings = get_option('zk_ai_settings', []);
            $model = $settings['model'] ?? 'claude-sonnet-4-20250514';

            // Veraltete Modelle durch aktuelle ersetzen
            $deprecated_models = [
                'claude-3-opus-20240229' => 'claude-sonnet-4-20250514',
                'claude-3-sonnet-20240229' => 'claude-sonnet-4-20250514',
                'claude-3-haiku-20240307' => 'claude-3-5-haiku-20241022',
                'gpt-4-turbo-preview' => 'gpt-4o',
            ];
            if (isset($deprecated_models[$model])) {
                $model = $deprecated_models[$model];
            }

            $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
                'timeout' => 180,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'x-api-key' => $api_key,
                    'anthropic-version' => '2023-06-01',
                ],
                'body' => json_encode([
                    'model' => $model,
                    'max_tokens' => 16384,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt]
                    ],
                ]),
            ]);

            if (is_wp_error($response)) {
                return $response;
            }

            $status = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            if ($status !== 200) {
                $error = json_decode($body, true);
                return new WP_Error('api_error', $error['error']['message'] ?? 'API-Fehler: ' . $status);
            }

            $data = json_decode($body, true);

            if (!isset($data['content'][0]['text'])) {
                return new WP_Error('parse_error', 'Ungültige API-Antwort');
            }

            $result_text = $data['content'][0]['text'];
            $result = json_decode($result_text, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                if (preg_match('/\{[\s\S]*\}/', $result_text, $m)) {
                    $result = json_decode($m[0], true);
                }
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return new WP_Error('parse_error', 'KI-Antwort konnte nicht geparst werden');
                }
            }

            return $result;
        }

        /**
         * OpenAI API
         */
        private function call_openai_api($prompt, $api_key) {
            $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
                'timeout' => 180,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key,
                ],
                'body' => json_encode([
                    'model' => 'gpt-4o',
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'max_tokens' => 16384,
                    'response_format' => ['type' => 'json_object'],
                ]),
            ]);

            if (is_wp_error($response)) {
                return $response;
            }

            $status = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            if ($status !== 200) {
                $error = json_decode($body, true);
                return new WP_Error('api_error', $error['error']['message'] ?? 'API-Fehler: ' . $status);
            }

            $data = json_decode($body, true);

            if (!isset($data['choices'][0]['message']['content'])) {
                return new WP_Error('parse_error', 'Ungültige API-Antwort');
            }

            $result = json_decode($data['choices'][0]['message']['content'], true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return new WP_Error('parse_error', 'KI-Antwort konnte nicht geparst werden');
            }

            return $result;
        }

        /**
         * Einzelne Artikel-PDFs erstellen
         */
        private function create_article_pdfs($source_pdf, $articles, $output_dir) {
            $pdf_dir = $output_dir . 'articles/';
            wp_mkdir_p($pdf_dir);

            foreach ($articles as $index => &$article) {
                $start = intval($article['start_page'] ?? 1);
                $end = intval($article['end_page'] ?? $start);

                if ($start <= 0) $start = 1;
                if ($end < $start) $end = $start;

                $article_pdf = $pdf_dir . 'article_' . ($index + 1) . '.pdf';

                // pdftk verwenden wenn verfügbar
                if ($this->command_exists('pdftk')) {
                    $command = sprintf(
                        'pdftk %s cat %d-%d output %s',
                        escapeshellarg($source_pdf),
                        $start,
                        $end,
                        escapeshellarg($article_pdf)
                    );
                    exec($command, $output, $return_var);

                    if ($return_var === 0 && file_exists($article_pdf)) {
                        $article['pdf_path'] = $article_pdf;
                        $article['pdf_url'] = str_replace(
                            $this->upload_dir['basedir'],
                            $this->upload_dir['baseurl'],
                            $article_pdf
                        );
                    }
                }
                // Alternativ: qpdf
                elseif ($this->command_exists('qpdf')) {
                    $command = sprintf(
                        'qpdf %s --pages . %d-%d -- %s',
                        escapeshellarg($source_pdf),
                        $start,
                        $end,
                        escapeshellarg($article_pdf)
                    );
                    exec($command, $output, $return_var);

                    if ($return_var === 0 && file_exists($article_pdf)) {
                        $article['pdf_path'] = $article_pdf;
                    }
                }
                // Alternativ: Ghostscript
                elseif ($this->command_exists('gs')) {
                    $command = sprintf(
                        'gs -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -dFirstPage=%d -dLastPage=%d -sOutputFile=%s %s',
                        $start,
                        $end,
                        escapeshellarg($article_pdf),
                        escapeshellarg($source_pdf)
                    );
                    exec($command, $output, $return_var);

                    if ($return_var === 0 && file_exists($article_pdf)) {
                        $article['pdf_path'] = $article_pdf;
                    }
                }
            }

            return $articles;
        }

        /**
         * ACF-Felder für Artikel setzen
         */
        private function set_article_fields($post_id, $article) {
            $fields = [
                'unterueberschrift' => $article['subtitle'] ?? '',
                'autoren' => $article['authors'] ?? '',
                'hauptautorin' => $article['main_author'] ?? '',
                'publikationsart' => $article['publication_type'] ?? '',
                'abstract-deutsch' => $article['abstract_de'] ?? '',
                'abstract' => $article['abstract_en'] ?? '',
                'keywords-deutsch' => $article['keywords_de'] ?? '',
                'keywords-englisch' => $article['keywords_en'] ?? '',
                'literatur' => $article['references'] ?? '',
            ];

            foreach ($fields as $key => $value) {
                if (!empty($value)) {
                    update_field($key, $value, $post_id);
                }
            }

            // Publikationsart auch als post_meta
            if (!empty($article['publication_type'])) {
                update_post_meta($post_id, 'publikationsart', $article['publication_type']);
            }
        }

        /**
         * Datei in Mediathek importieren
         */
        private function import_to_media_library($file_path, $title, $parent_id = 0) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');

            $upload = wp_upload_bits(
                basename($file_path),
                null,
                file_get_contents($file_path)
            );

            if ($upload['error']) {
                return false;
            }

            $filetype = wp_check_filetype($upload['file']);

            $attachment = [
                'post_mime_type' => $filetype['type'],
                'post_title' => sanitize_text_field($title),
                'post_content' => '',
                'post_status' => 'inherit',
                'post_parent' => $parent_id,
            ];

            $attach_id = wp_insert_attachment($attachment, $upload['file'], $parent_id);

            if (!is_wp_error($attach_id)) {
                $attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);
                wp_update_attachment_metadata($attach_id, $attach_data);
                return $attach_id;
            }

            return false;
        }

        /**
         * AI-Einstellungen abrufen
         */
        public function ajax_get_ai_settings() {
            check_ajax_referer('zk_admin_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $settings = get_option('zk_ai_settings', [
                'provider' => 'anthropic',
                'api_key' => '',
                'model' => 'claude-sonnet-4-20250514',
            ]);

            if (!empty($settings['api_key'])) {
                $settings['api_key_masked'] = substr($settings['api_key'], 0, 10) . '...' . substr($settings['api_key'], -4);
                $settings['has_key'] = true;
            } else {
                $settings['has_key'] = false;
            }

            unset($settings['api_key']);

            wp_send_json_success($settings);
        }

        /**
         * AI-Einstellungen speichern
         */
        public function ajax_save_ai_settings() {
            check_ajax_referer('zk_admin_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $provider = sanitize_key($_POST['provider'] ?? 'anthropic');
            $api_key = sanitize_text_field($_POST['api_key'] ?? '');
            $model = sanitize_text_field($_POST['model'] ?? 'claude-sonnet-4-20250514');

            $settings = get_option('zk_ai_settings', []);

            $settings['provider'] = $provider;
            $settings['model'] = $model;

            if (!empty($api_key) && strpos($api_key, '...') === false) {
                $settings['api_key'] = $api_key;
            }

            update_option('zk_ai_settings', $settings);

            wp_send_json_success(['message' => 'Einstellungen gespeichert']);
        }

        /**
         * Prüft ob Shell-Befehl verfügbar
         */
        private function command_exists($command) {
            $which = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? 'where' : 'which';
            exec("$which $command 2>&1", $output, $return_var);
            return $return_var === 0;
        }

        /**
         * Temporäre Dateien aufräumen
         */
        public function cleanup_temp_files() {
            $dirs = [$this->temp_dir, $this->import_dir];
            $now = time();
            $max_age = 86400; // 24 Stunden

            foreach ($dirs as $dir) {
                if (!is_dir($dir)) continue;

                $items = scandir($dir);
                foreach ($items as $item) {
                    if ($item === '.' || $item === '..') continue;

                    $path = $dir . $item;
                    $mtime = filemtime($path);

                    if (($now - $mtime) > $max_age) {
                        if (is_dir($path)) {
                            $this->delete_directory($path);
                        } else {
                            unlink($path);
                        }
                    }
                }
            }
        }

        /**
         * Verzeichnis rekursiv löschen
         */
        private function delete_directory($dir) {
            if (!is_dir($dir)) return;

            $files = array_diff(scandir($dir), ['.', '..']);

            foreach ($files as $file) {
                $path = $dir . '/' . $file;
                is_dir($path) ? $this->delete_directory($path) : unlink($path);
            }

            rmdir($dir);
        }
    }
}
