<?php
/**
 * PDF Import mit KI-Unterstützung
 *
 * Importiert PDFs, extrahiert Text und Bilder und füllt Felder
 * automatisch mit Hilfe von Claude AI.
 *
 * @package DGPTM_Zeitschrift_Kardiotechnik
 * @since 1.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('ZK_PDF_Import')) {

    class ZK_PDF_Import {

        private static $instance = null;
        private $upload_dir;
        private $temp_dir;

        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            $this->upload_dir = wp_upload_dir();
            $this->temp_dir = $this->upload_dir['basedir'] . '/zk-pdf-temp/';

            // Temp-Verzeichnis erstellen
            if (!file_exists($this->temp_dir)) {
                wp_mkdir_p($this->temp_dir);
            }

            // AJAX Handler registrieren
            add_action('wp_ajax_zk_upload_pdf', [$this, 'ajax_upload_pdf']);
            add_action('wp_ajax_zk_extract_pdf', [$this, 'ajax_extract_pdf']);
            add_action('wp_ajax_zk_ai_analyze', [$this, 'ajax_ai_analyze']);
            add_action('wp_ajax_zk_import_article', [$this, 'ajax_import_article']);
            add_action('wp_ajax_zk_get_ai_settings', [$this, 'ajax_get_ai_settings']);
            add_action('wp_ajax_zk_save_ai_settings', [$this, 'ajax_save_ai_settings']);
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

            // Datei speichern
            $filename = sanitize_file_name($file['name']);
            $unique_id = uniqid('pdf_');
            $filepath = $this->temp_dir . $unique_id . '_' . $filename;

            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                wp_send_json_error(['message' => 'Fehler beim Speichern']);
            }

            wp_send_json_success([
                'file_id' => $unique_id,
                'filename' => $filename,
                'filepath' => $filepath,
                'size' => filesize($filepath),
            ]);
        }

        /**
         * PDF-Inhalt extrahieren
         */
        public function ajax_extract_pdf() {
            check_ajax_referer('zk_admin_nonce', 'nonce');

            if (!current_user_can('edit_posts')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $file_id = sanitize_text_field($_POST['file_id'] ?? '');
            $filename = sanitize_file_name($_POST['filename'] ?? '');

            if (empty($file_id) || empty($filename)) {
                wp_send_json_error(['message' => 'Ungültige Parameter']);
            }

            $filepath = $this->temp_dir . $file_id . '_' . $filename;

            if (!file_exists($filepath)) {
                wp_send_json_error(['message' => 'PDF nicht gefunden']);
            }

            // Text extrahieren
            $text = $this->extract_text($filepath);
            $images = $this->extract_images($filepath, $file_id);
            $metadata = $this->extract_metadata($filepath);

            wp_send_json_success([
                'text' => $text,
                'images' => $images,
                'metadata' => $metadata,
                'page_count' => $metadata['pages'] ?? 1,
            ]);
        }

        /**
         * KI-Analyse durchführen
         */
        public function ajax_ai_analyze() {
            check_ajax_referer('zk_admin_nonce', 'nonce');

            if (!current_user_can('edit_posts')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $text = wp_unslash($_POST['text'] ?? '');
            $metadata = isset($_POST['metadata']) ? json_decode(wp_unslash($_POST['metadata']), true) : [];

            if (empty($text)) {
                wp_send_json_error(['message' => 'Kein Text zum Analysieren']);
            }

            // AI-Einstellungen laden
            $settings = get_option('zk_ai_settings', []);
            $api_key = $settings['api_key'] ?? '';
            $provider = $settings['provider'] ?? 'anthropic';

            if (empty($api_key)) {
                wp_send_json_error([
                    'message' => 'Kein API-Key konfiguriert',
                    'need_config' => true
                ]);
            }

            // KI-Analyse durchführen
            $result = $this->analyze_with_ai($text, $metadata, $api_key, $provider);

            if (is_wp_error($result)) {
                wp_send_json_error(['message' => $result->get_error_message()]);
            }

            wp_send_json_success($result);
        }

        /**
         * Artikel aus PDF-Import erstellen
         */
        public function ajax_import_article() {
            check_ajax_referer('zk_admin_nonce', 'nonce');

            if (!current_user_can('edit_posts')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $data = isset($_POST['article']) ? json_decode(wp_unslash($_POST['article']), true) : [];

            if (empty($data)) {
                wp_send_json_error(['message' => 'Keine Artikeldaten']);
            }

            // Artikel erstellen
            $post_id = wp_insert_post([
                'post_type' => ZK_PUBLIKATION_TYPE,
                'post_title' => sanitize_text_field($data['title'] ?? 'Importierter Artikel'),
                'post_content' => wp_kses_post($data['content'] ?? ''),
                'post_status' => 'draft',
                'post_author' => get_current_user_id(),
            ]);

            if (is_wp_error($post_id)) {
                wp_send_json_error(['message' => $post_id->get_error_message()]);
            }

            // ACF-Felder setzen
            $acf_fields = [
                'unterueberschrift' => $data['subtitle'] ?? '',
                'autoren' => $data['authors'] ?? '',
                'hauptautorin' => $data['main_author'] ?? '',
                'abstract-deutsch' => $data['abstract_de'] ?? '',
                'abstract' => $data['abstract_en'] ?? '',
                'keywords-deutsch' => $data['keywords_de'] ?? '',
                'keywords-englisch' => $data['keywords_en'] ?? '',
                'publikationsart' => $data['publication_type'] ?? '',
                'literatur' => $data['references'] ?? '',
                'doi' => $data['doi'] ?? '',
            ];

            foreach ($acf_fields as $key => $value) {
                if (!empty($value)) {
                    update_field($key, $value, $post_id);
                }
            }

            // Bilder importieren und verknüpfen
            if (!empty($data['images']) && is_array($data['images'])) {
                $this->import_images($post_id, $data['images']);
            }

            // PDF als Anhang speichern
            if (!empty($data['pdf_path']) && file_exists($data['pdf_path'])) {
                $this->attach_pdf($post_id, $data['pdf_path'], $data['original_filename'] ?? 'artikel.pdf');
            }

            wp_send_json_success([
                'post_id' => $post_id,
                'edit_url' => admin_url('post.php?post=' . $post_id . '&action=edit'),
                'message' => 'Artikel erfolgreich importiert',
            ]);
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

            // API-Key maskieren
            if (!empty($settings['api_key'])) {
                $settings['api_key_masked'] = substr($settings['api_key'], 0, 10) . '...' . substr($settings['api_key'], -4);
                $settings['has_key'] = true;
            } else {
                $settings['has_key'] = false;
            }

            unset($settings['api_key']); // Niemals den vollen Key zurückgeben

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

            // API-Key nur aktualisieren wenn neuer eingegeben wurde
            if (!empty($api_key) && strpos($api_key, '...') === false) {
                $settings['api_key'] = $api_key;
            }

            update_option('zk_ai_settings', $settings);

            wp_send_json_success(['message' => 'Einstellungen gespeichert']);
        }

        /**
         * Text aus PDF extrahieren
         */
        private function extract_text($filepath) {
            $text = '';

            // Methode 1: pdftotext (wenn installiert)
            if ($this->command_exists('pdftotext')) {
                $output_file = $this->temp_dir . uniqid('txt_') . '.txt';
                $command = sprintf(
                    'pdftotext -layout %s %s 2>&1',
                    escapeshellarg($filepath),
                    escapeshellarg($output_file)
                );
                exec($command, $output, $return_var);

                if ($return_var === 0 && file_exists($output_file)) {
                    $text = file_get_contents($output_file);
                    unlink($output_file);
                }
            }

            // Methode 2: PHP-basiert mit Regex (Fallback)
            if (empty($text)) {
                $text = $this->extract_text_php($filepath);
            }

            // Text bereinigen
            $text = $this->clean_extracted_text($text);

            return $text;
        }

        /**
         * PHP-basierte Textextraktion (Fallback)
         */
        private function extract_text_php($filepath) {
            $content = file_get_contents($filepath);

            // Streams dekomprimieren
            $text = '';

            // Einfache Textextraktion aus PDF-Streams
            if (preg_match_all('/stream\s*(.*?)\s*endstream/s', $content, $matches)) {
                foreach ($matches[1] as $stream) {
                    // Versuche FlateDecode zu dekomprimieren
                    $decoded = @gzuncompress($stream);
                    if ($decoded === false) {
                        $decoded = @gzinflate($stream);
                    }
                    if ($decoded !== false) {
                        // Text aus decodiertem Stream extrahieren
                        if (preg_match_all('/\[(.*?)\]\s*TJ/s', $decoded, $textMatches)) {
                            foreach ($textMatches[1] as $textPart) {
                                // Extrahiere Strings aus TJ-Operator
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
         * Extrahierten Text bereinigen
         */
        private function clean_extracted_text($text) {
            // Mehrfache Leerzeichen reduzieren
            $text = preg_replace('/[ \t]+/', ' ', $text);

            // Mehrfache Zeilenumbrüche reduzieren
            $text = preg_replace('/\n{3,}/', "\n\n", $text);

            // Seitenumbrüche markieren
            $text = preg_replace('/\f/', "\n\n--- Seitenumbruch ---\n\n", $text);

            // Trim
            $text = trim($text);

            return $text;
        }

        /**
         * Bilder aus PDF extrahieren
         */
        private function extract_images($filepath, $file_id) {
            $images = [];
            $image_dir = $this->temp_dir . $file_id . '_images/';

            if (!file_exists($image_dir)) {
                wp_mkdir_p($image_dir);
            }

            // Methode 1: pdfimages (wenn installiert)
            if ($this->command_exists('pdfimages')) {
                $command = sprintf(
                    'pdfimages -all %s %s 2>&1',
                    escapeshellarg($filepath),
                    escapeshellarg($image_dir . 'img')
                );
                exec($command, $output, $return_var);

                if ($return_var === 0) {
                    $files = glob($image_dir . 'img*');
                    foreach ($files as $file) {
                        $images[] = [
                            'path' => $file,
                            'url' => str_replace(
                                $this->upload_dir['basedir'],
                                $this->upload_dir['baseurl'],
                                $file
                            ),
                            'filename' => basename($file),
                            'size' => filesize($file),
                        ];
                    }
                }
            }

            return $images;
        }

        /**
         * PDF-Metadaten extrahieren
         */
        private function extract_metadata($filepath) {
            $metadata = [
                'pages' => 1,
                'title' => '',
                'author' => '',
                'subject' => '',
                'creator' => '',
            ];

            // Methode 1: pdfinfo (wenn installiert)
            if ($this->command_exists('pdfinfo')) {
                $command = sprintf('pdfinfo %s 2>&1', escapeshellarg($filepath));
                exec($command, $output, $return_var);

                if ($return_var === 0) {
                    foreach ($output as $line) {
                        if (preg_match('/^Pages:\s*(\d+)/', $line, $m)) {
                            $metadata['pages'] = intval($m[1]);
                        } elseif (preg_match('/^Title:\s*(.+)$/', $line, $m)) {
                            $metadata['title'] = trim($m[1]);
                        } elseif (preg_match('/^Author:\s*(.+)$/', $line, $m)) {
                            $metadata['author'] = trim($m[1]);
                        } elseif (preg_match('/^Subject:\s*(.+)$/', $line, $m)) {
                            $metadata['subject'] = trim($m[1]);
                        }
                    }
                }
            }

            // Fallback: Aus PDF-Header lesen
            if (empty($metadata['title'])) {
                $content = file_get_contents($filepath, false, null, 0, 10000);

                if (preg_match('/\/Title\s*\(([^)]+)\)/', $content, $m)) {
                    $metadata['title'] = $this->decode_pdf_string($m[1]);
                }
                if (preg_match('/\/Author\s*\(([^)]+)\)/', $content, $m)) {
                    $metadata['author'] = $this->decode_pdf_string($m[1]);
                }
            }

            return $metadata;
        }

        /**
         * PDF-String dekodieren
         */
        private function decode_pdf_string($str) {
            // Oktal-Escapes ersetzen
            $str = preg_replace_callback('/\\\\([0-7]{1,3})/', function($m) {
                return chr(octdec($m[1]));
            }, $str);

            // Unicode-Escapes
            if (substr($str, 0, 2) === "\xFE\xFF") {
                $str = mb_convert_encoding(substr($str, 2), 'UTF-8', 'UTF-16BE');
            }

            return $str;
        }

        /**
         * KI-Analyse mit Claude/OpenAI
         */
        private function analyze_with_ai($text, $metadata, $api_key, $provider = 'anthropic') {
            // Text kürzen wenn zu lang
            $max_chars = 100000;
            if (strlen($text) > $max_chars) {
                $text = substr($text, 0, $max_chars) . "\n\n[Text gekürzt...]";
            }

            $prompt = $this->build_analysis_prompt($text, $metadata);

            if ($provider === 'anthropic') {
                return $this->call_anthropic_api($prompt, $api_key);
            } elseif ($provider === 'openai') {
                return $this->call_openai_api($prompt, $api_key);
            }

            return new WP_Error('invalid_provider', 'Ungültiger KI-Provider');
        }

        /**
         * Analyse-Prompt erstellen
         */
        private function build_analysis_prompt($text, $metadata) {
            $prompt = <<<PROMPT
Du bist ein Experte für wissenschaftliche Publikationen im medizinischen Bereich, speziell Kardiotechnik.
Analysiere den folgenden extrahierten Text aus einem PDF-Artikel und extrahiere die relevanten Informationen.

Antworte NUR mit einem validen JSON-Objekt (ohne Markdown-Codeblöcke) mit folgender Struktur:
{
    "title": "Haupttitel des Artikels",
    "subtitle": "Untertitel falls vorhanden",
    "authors": "Alle Autoren als kommaseparierte Liste",
    "main_author": "Erstautor/Hauptautorin",
    "abstract_de": "Deutsches Abstract/Zusammenfassung",
    "abstract_en": "Englisches Abstract falls vorhanden",
    "keywords_de": "Deutsche Schlüsselwörter, kommasepariert",
    "keywords_en": "Englische Keywords falls vorhanden",
    "publication_type": "Art der Publikation (Originalarbeit, Übersichtsarbeit, Fallbericht, Editorial, Tutorial, etc.)",
    "content": "Der Haupttext des Artikels im HTML-Format mit <h2>, <h3>, <p>, <ul>, <ol>, <strong>, <em> Tags",
    "references": "Literaturverzeichnis im HTML-Format als nummerierte Liste",
    "doi": "DOI falls vorhanden",
    "confidence": {
        "title": 0.95,
        "authors": 0.9,
        "abstract": 0.85
    }
}

Wichtige Hinweise:
- Der "content" soll den Haupttext enthalten, formatiert mit HTML-Tags für Überschriften, Absätze und Listen
- Entferne Kopf-/Fußzeilen, Seitenzahlen und andere Layout-Elemente
- Behalte die wissenschaftliche Struktur (Einleitung, Methoden, Ergebnisse, Diskussion) bei
- Bei Unsicherheit setze "confidence" entsprechend niedriger
- Falls etwas nicht gefunden wird, verwende einen leeren String

PDF-Metadaten:
- PDF-Titel: {$metadata['title']}
- PDF-Autor: {$metadata['author']}

--- Extrahierter Text ---

{$text}

--- Ende des Textes ---

Antworte NUR mit dem JSON-Objekt, ohne zusätzlichen Text oder Erklärungen.
PROMPT;

            return $prompt;
        }

        /**
         * Anthropic Claude API aufrufen
         */
        private function call_anthropic_api($prompt, $api_key) {
            $settings = get_option('zk_ai_settings', []);
            $model = $settings['model'] ?? 'claude-sonnet-4-20250514';

            $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
                'timeout' => 120,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'x-api-key' => $api_key,
                    'anthropic-version' => '2023-06-01',
                ],
                'body' => json_encode([
                    'model' => $model,
                    'max_tokens' => 8192,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ]
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
                return new WP_Error(
                    'api_error',
                    $error['error']['message'] ?? 'API-Fehler: ' . $status
                );
            }

            $data = json_decode($body, true);

            if (!isset($data['content'][0]['text'])) {
                return new WP_Error('parse_error', 'Ungültige API-Antwort');
            }

            $result_text = $data['content'][0]['text'];

            // JSON parsen
            $result = json_decode($result_text, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                // Versuche JSON aus Text zu extrahieren
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
         * OpenAI API aufrufen
         */
        private function call_openai_api($prompt, $api_key) {
            $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
                'timeout' => 120,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key,
                ],
                'body' => json_encode([
                    'model' => 'gpt-4-turbo-preview',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ]
                    ],
                    'max_tokens' => 8192,
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
                return new WP_Error(
                    'api_error',
                    $error['error']['message'] ?? 'API-Fehler: ' . $status
                );
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
         * Bilder in WordPress importieren
         */
        private function import_images($post_id, $images) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');

            $attachment_ids = [];

            foreach ($images as $image) {
                if (!file_exists($image['path'])) {
                    continue;
                }

                // In Uploads-Verzeichnis kopieren
                $upload = wp_upload_bits(
                    basename($image['path']),
                    null,
                    file_get_contents($image['path'])
                );

                if ($upload['error']) {
                    continue;
                }

                // Attachment erstellen
                $attachment = [
                    'post_mime_type' => wp_check_filetype($upload['file'])['type'],
                    'post_title' => sanitize_file_name(pathinfo($upload['file'], PATHINFO_FILENAME)),
                    'post_content' => '',
                    'post_status' => 'inherit',
                    'post_parent' => $post_id,
                ];

                $attach_id = wp_insert_attachment($attachment, $upload['file'], $post_id);

                if (!is_wp_error($attach_id)) {
                    $attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);
                    wp_update_attachment_metadata($attach_id, $attach_data);
                    $attachment_ids[] = $attach_id;
                }
            }

            // Erstes Bild als Featured Image setzen
            if (!empty($attachment_ids) && !has_post_thumbnail($post_id)) {
                set_post_thumbnail($post_id, $attachment_ids[0]);
            }

            return $attachment_ids;
        }

        /**
         * PDF als Attachment speichern
         */
        private function attach_pdf($post_id, $pdf_path, $filename) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');

            $upload = wp_upload_bits(
                $filename,
                null,
                file_get_contents($pdf_path)
            );

            if ($upload['error']) {
                return false;
            }

            $attachment = [
                'post_mime_type' => 'application/pdf',
                'post_title' => sanitize_file_name(pathinfo($filename, PATHINFO_FILENAME)),
                'post_content' => '',
                'post_status' => 'inherit',
                'post_parent' => $post_id,
            ];

            $attach_id = wp_insert_attachment($attachment, $upload['file'], $post_id);

            if (!is_wp_error($attach_id)) {
                // PDF-Volltext-Feld aktualisieren
                update_field('pdf-volltext', $attach_id, $post_id);
                return $attach_id;
            }

            return false;
        }

        /**
         * Prüft ob ein Shell-Befehl verfügbar ist
         */
        private function command_exists($command) {
            $which = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? 'where' : 'which';
            exec("$which $command 2>&1", $output, $return_var);
            return $return_var === 0;
        }

        /**
         * Temporäre Dateien aufräumen (älter als 24h)
         */
        public function cleanup_temp_files() {
            $files = glob($this->temp_dir . '*');
            $now = time();

            foreach ($files as $file) {
                if (is_file($file) && ($now - filemtime($file) > 86400)) {
                    unlink($file);
                } elseif (is_dir($file) && ($now - filemtime($file) > 86400)) {
                    $this->delete_directory($file);
                }
            }
        }

        /**
         * Verzeichnis rekursiv löschen
         */
        private function delete_directory($dir) {
            if (!is_dir($dir)) {
                return;
            }

            $files = array_diff(scandir($dir), ['.', '..']);

            foreach ($files as $file) {
                $path = $dir . '/' . $file;
                is_dir($path) ? $this->delete_directory($path) : unlink($path);
            }

            rmdir($dir);
        }
    }
}
