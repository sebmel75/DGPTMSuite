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

            if (empty($import_data) || !is_array($import_data)) {
                error_log('ZK Import: import.json ist leer oder ungültig');
                wp_send_json_error(['message' => 'Import-Daten ungültig. Bitte PDF erneut hochladen.']);
            }

            $pdf_path = $import_data['filepath'] ?? null;

            if (empty($pdf_path) || !file_exists($pdf_path)) {
                error_log('ZK Import: PDF-Pfad nicht gefunden: ' . ($pdf_path ?? 'NULL'));
                error_log('ZK Import: import.json Inhalt: ' . print_r($import_data, true));
                wp_send_json_error(['message' => 'PDF-Datei nicht gefunden. Bitte erneut hochladen.']);
            }

            // AI-Einstellungen laden und prüfen
            $settings = get_option('zk_ai_settings', []);
            $api_key = $settings['api_key'] ?? '';
            $provider = $settings['provider'] ?? 'anthropic';

            error_log('=== ZK Extraktion Start ===');
            error_log('Import-ID: ' . $import_id);
            error_log('AI Settings geladen: ' . print_r(array_keys($settings), true));
            error_log('Provider: ' . $provider);
            error_log('API-Key vorhanden: ' . (!empty($api_key) ? 'ja (Länge: ' . strlen($api_key) . ')' : 'NEIN!'));

            if (empty($api_key)) {
                error_log('ZK Extraktion: ABBRUCH - Kein API-Key!');
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

            // Prüfen ob PDF gescannt ist (wenig Text pro Seite)
            $chars_per_page = $page_count > 0 ? strlen($full_text) / $page_count : 0;
            $is_scanned_pdf = $chars_per_page < 200; // Weniger als 200 Zeichen pro Seite = wahrscheinlich gescannt

            error_log('ZK PDF: Zeichen pro Seite: ' . round($chars_per_page));
            error_log('ZK PDF: Gescanntes PDF erkannt: ' . ($is_scanned_pdf ? 'JA' : 'nein'));

            // 5. KI-Analyse durchführen
            if ($is_scanned_pdf && $provider === 'anthropic') {
                // Gescanntes PDF: Claude Vision API verwenden
                error_log('ZK PDF: Verwende Claude Vision für gescanntes PDF');
                $analysis = $this->analyze_scanned_pdf_with_vision(
                    $pdf_path,
                    $import_path,
                    $page_count,
                    $api_key
                );
            } else {
                // Normales PDF: Textbasierte Analyse
                $analysis = $this->analyze_issue_with_ai(
                    $full_text,
                    $page_count,
                    $api_key,
                    $provider
                );
            }

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

            error_log('=== ZK PDF Text Extraktion ===');
            error_log('PDF Pfad: ' . $pdf_path);
            error_log('PDF existiert: ' . (file_exists($pdf_path) ? 'ja' : 'NEIN!'));

            // Methode 1: pdftotext mit Layout
            $pdftotext_available = $this->command_exists('pdftotext');
            error_log('pdftotext verfügbar: ' . ($pdftotext_available ? 'ja' : 'nein'));

            if ($pdftotext_available) {
                $output_file = $this->temp_dir . uniqid('txt_') . '.txt';
                $command = sprintf(
                    'pdftotext -layout %s %s 2>&1',
                    escapeshellarg($pdf_path),
                    escapeshellarg($output_file)
                );
                exec($command, $output, $return_var);
                error_log('pdftotext Return: ' . $return_var);

                if ($return_var === 0 && file_exists($output_file)) {
                    $text = file_get_contents($output_file);
                    unlink($output_file);
                    error_log('pdftotext Ergebnis: ' . strlen($text) . ' Zeichen');
                }
            }

            // Fallback: PHP-basierte Extraktion
            if (empty($text)) {
                error_log('Verwende PHP Fallback für Textextraktion...');
                $text = $this->extract_text_php($pdf_path);
                error_log('PHP Fallback Ergebnis: ' . strlen($text) . ' Zeichen');
            }

            $cleaned = $this->clean_text($text);
            error_log('Nach Bereinigung: ' . strlen($cleaned) . ' Zeichen');

            return $cleaned;
        }

        /**
         * PHP-basierte Textextraktion (verbessert)
         */
        private function extract_text_php($pdf_path) {
            $content = file_get_contents($pdf_path);
            if (empty($content)) {
                error_log('ZK PDF: Datei konnte nicht gelesen werden');
                return '';
            }

            error_log('ZK PDF: Dateigröße ' . strlen($content) . ' Bytes');

            $text = '';
            $streams_found = 0;
            $streams_decoded = 0;

            // Alle Streams finden und dekodieren
            if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $content, $matches)) {
                $streams_found = count($matches[1]);

                foreach ($matches[1] as $stream) {
                    $decoded = $this->decode_pdf_stream($stream);
                    if (!empty($decoded)) {
                        $streams_decoded++;
                        $extracted = $this->extract_text_from_stream($decoded);
                        if (!empty($extracted)) {
                            $text .= $extracted . "\n";
                        }
                    }
                }
            }

            error_log("ZK PDF: $streams_found Streams gefunden, $streams_decoded dekodiert");
            error_log('ZK PDF: Extrahierter Text: ' . strlen($text) . ' Zeichen');

            // Fallback: Direkte Textsuche im PDF
            if (strlen($text) < 500) {
                error_log('ZK PDF: Versuche direkte Textextraktion...');
                $direct_text = $this->extract_text_direct($content);
                if (strlen($direct_text) > strlen($text)) {
                    $text = $direct_text;
                    error_log('ZK PDF: Direkte Extraktion: ' . strlen($text) . ' Zeichen');
                }
            }

            return $text;
        }

        /**
         * PDF Stream dekodieren
         */
        private function decode_pdf_stream($stream) {
            // Versuche verschiedene Dekodierungsmethoden
            $decoded = @gzuncompress($stream);
            if ($decoded !== false) {
                return $decoded;
            }

            $decoded = @gzinflate($stream);
            if ($decoded !== false) {
                return $decoded;
            }

            // Versuche mit Offset (manche PDFs haben Header)
            for ($i = 0; $i < 10; $i++) {
                $decoded = @gzinflate(substr($stream, $i));
                if ($decoded !== false) {
                    return $decoded;
                }
            }

            // Stream ist möglicherweise nicht komprimiert
            if (preg_match('/[BT|Tj|TJ]/', $stream)) {
                return $stream;
            }

            return '';
        }

        /**
         * Text aus dekodiertem Stream extrahieren
         */
        private function extract_text_from_stream($stream) {
            $text = '';

            // Methode 1: BT...ET Textblöcke
            if (preg_match_all('/BT\s*(.*?)\s*ET/s', $stream, $btMatches)) {
                foreach ($btMatches[1] as $block) {
                    $text .= $this->parse_text_block($block) . ' ';
                }
            }

            // Methode 2: TJ Arrays (häufigste Methode)
            if (preg_match_all('/\[((?:[^][]|\[(?:[^][])*\])*)\]\s*TJ/s', $stream, $tjMatches)) {
                foreach ($tjMatches[1] as $tjArray) {
                    $text .= $this->parse_tj_array($tjArray) . ' ';
                }
            }

            // Methode 3: Einfache Tj Strings
            if (preg_match_all('/\(([^)]*)\)\s*Tj/s', $stream, $tjSimple)) {
                foreach ($tjSimple[1] as $str) {
                    $text .= $this->decode_pdf_string($str) . ' ';
                }
            }

            // Methode 4: Hex-Strings
            if (preg_match_all('/<([0-9A-Fa-f]+)>\s*Tj/s', $stream, $hexMatches)) {
                foreach ($hexMatches[1] as $hex) {
                    $text .= $this->decode_hex_string($hex) . ' ';
                }
            }

            return trim($text);
        }

        /**
         * Textblock parsen
         */
        private function parse_text_block($block) {
            $text = '';

            // TJ Arrays
            if (preg_match_all('/\[(.*?)\]\s*TJ/s', $block, $matches)) {
                foreach ($matches[1] as $arr) {
                    $text .= $this->parse_tj_array($arr);
                }
            }

            // Einfache Tj
            if (preg_match_all('/\(([^)]*)\)\s*Tj/s', $block, $matches)) {
                foreach ($matches[1] as $str) {
                    $text .= $this->decode_pdf_string($str);
                }
            }

            return $text;
        }

        /**
         * TJ Array parsen
         */
        private function parse_tj_array($array) {
            $text = '';

            // Strings in Klammern
            if (preg_match_all('/\(([^)]*)\)/', $array, $matches)) {
                foreach ($matches[1] as $str) {
                    $text .= $this->decode_pdf_string($str);
                }
            }

            // Hex-Strings
            if (preg_match_all('/<([0-9A-Fa-f]+)>/', $array, $matches)) {
                foreach ($matches[1] as $hex) {
                    $text .= $this->decode_hex_string($hex);
                }
            }

            return $text;
        }

        /**
         * PDF String dekodieren
         */
        private function decode_pdf_string($str) {
            // Escape-Sequenzen ersetzen
            $str = str_replace(['\\n', '\\r', '\\t', '\\(', '\\)', '\\\\'], ["\n", "\r", "\t", '(', ')', '\\'], $str);

            // Octal-Escape-Sequenzen
            $str = preg_replace_callback('/\\\\([0-7]{1,3})/', function($m) {
                return chr(octdec($m[1]));
            }, $str);

            return $str;
        }

        /**
         * Hex-String dekodieren
         */
        private function decode_hex_string($hex) {
            $text = '';
            $hex = preg_replace('/\s+/', '', $hex);

            // UTF-16BE (beginnt oft mit FEFF)
            if (strlen($hex) >= 4 && (substr($hex, 0, 4) === 'FEFF' || strlen($hex) % 4 === 0)) {
                for ($i = 0; $i < strlen($hex); $i += 4) {
                    $char = hexdec(substr($hex, $i, 4));
                    if ($char > 31 && $char < 127) {
                        $text .= chr($char);
                    } elseif ($char > 127) {
                        $text .= mb_chr($char, 'UTF-8');
                    }
                }
            } else {
                // Einfache Hex-Kodierung
                for ($i = 0; $i < strlen($hex); $i += 2) {
                    $char = hexdec(substr($hex, $i, 2));
                    if ($char > 31) {
                        $text .= chr($char);
                    }
                }
            }

            return $text;
        }

        /**
         * Direkte Textextraktion (Fallback)
         */
        private function extract_text_direct($content) {
            $text = '';

            // Suche nach lesbarem Text zwischen Markern
            // Viele PDFs haben Text in /Contents oder als direkte Strings

            // Methode 1: Text in Td/TD Positionen
            if (preg_match_all('/\(([^()]{3,})\)\s*(?:Tj|TJ|\'|")/s', $content, $matches)) {
                foreach ($matches[1] as $str) {
                    $decoded = $this->decode_pdf_string($str);
                    // Nur wenn es wie Text aussieht
                    if (preg_match('/[a-zA-ZäöüÄÖÜß]{2,}/', $decoded)) {
                        $text .= $decoded . ' ';
                    }
                }
            }

            // Methode 2: Unicode-Text
            if (preg_match_all('/<([0-9A-Fa-f]{8,})>\s*Tj/s', $content, $matches)) {
                foreach ($matches[1] as $hex) {
                    $decoded = $this->decode_hex_string($hex);
                    if (preg_match('/[a-zA-ZäöüÄÖÜß]{2,}/', $decoded)) {
                        $text .= $decoded . ' ';
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
         * UTF-8 Text bereinigen (entfernt ungültige Zeichen)
         */
        private function sanitize_utf8($text) {
            if (empty($text)) {
                return '';
            }

            // Entferne NULL-Bytes
            $text = str_replace("\0", '', $text);

            // Konvertiere zu UTF-8 falls nötig
            $encoding = mb_detect_encoding($text, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
            if ($encoding && $encoding !== 'UTF-8') {
                $text = mb_convert_encoding($text, 'UTF-8', $encoding);
            }

            // Entferne ungültige UTF-8 Sequenzen
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');

            // Entferne nicht-druckbare Steuerzeichen (außer Newline, Tab)
            $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);

            // Ersetze problematische Unicode-Zeichen
            $text = preg_replace('/[\x{FFFE}\x{FFFF}]/u', '', $text);

            return $text;
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
         * Gescanntes PDF mit Claude Vision analysieren
         */
        private function analyze_scanned_pdf_with_vision($pdf_path, $import_path, $page_count, $api_key) {
            error_log('=== ZK Vision Analyse Start ===');

            // 1. PDF-Seiten als Bilder extrahieren
            $page_images = $this->extract_pages_as_images($pdf_path, $import_path, $page_count);

            if (empty($page_images)) {
                error_log('ZK Vision: Keine Seitenbilder extrahiert');
                return new WP_Error('no_images', 'Konnte keine Seitenbilder aus dem PDF extrahieren');
            }

            error_log('ZK Vision: ' . count($page_images) . ' Seitenbilder extrahiert');

            // 2. Bilder für API vorbereiten (max 20 Seiten wegen Token-Limit)
            $max_pages = min(count($page_images), 20);
            $image_contents = [];

            for ($i = 0; $i < $max_pages; $i++) {
                $image_path = $page_images[$i];
                if (file_exists($image_path)) {
                    $image_data = file_get_contents($image_path);
                    $base64 = base64_encode($image_data);
                    $mime_type = 'image/jpeg';

                    // Dateigröße prüfen (max 5MB pro Bild)
                    if (strlen($image_data) > 5 * 1024 * 1024) {
                        error_log('ZK Vision: Bild ' . ($i + 1) . ' zu groß, überspringe');
                        continue;
                    }

                    $image_contents[] = [
                        'type' => 'image',
                        'source' => [
                            'type' => 'base64',
                            'media_type' => $mime_type,
                            'data' => $base64,
                        ],
                    ];

                    error_log('ZK Vision: Seite ' . ($i + 1) . ' hinzugefügt (' . round(strlen($image_data) / 1024) . ' KB)');
                }
            }

            if (empty($image_contents)) {
                return new WP_Error('no_valid_images', 'Keine gültigen Bilder für Vision-Analyse');
            }

            // 3. Prompt für Vision-Analyse
            $prompt_text = $this->build_vision_analysis_prompt($page_count);

            // Prompt als Text-Content hinzufügen
            $image_contents[] = [
                'type' => 'text',
                'text' => $prompt_text,
            ];

            // 4. Claude Vision API aufrufen
            return $this->call_anthropic_vision_api($image_contents, $api_key);
        }

        /**
         * PDF-Seiten als Bilder extrahieren
         */
        private function extract_pages_as_images($pdf_path, $output_dir, $page_count) {
            $images = [];
            $pages_dir = $output_dir . 'pages/';
            wp_mkdir_p($pages_dir);

            // pdftoppm verfügbar?
            if ($this->command_exists('pdftoppm')) {
                error_log('ZK Vision: Verwende pdftoppm für Seitenextraktion');

                $command = sprintf(
                    'pdftoppm -jpeg -r 150 %s %s 2>&1',
                    escapeshellarg($pdf_path),
                    escapeshellarg($pages_dir . 'page')
                );
                exec($command, $output, $return_var);

                error_log('ZK Vision: pdftoppm Return: ' . $return_var);

                if ($return_var === 0) {
                    // Generierte Bilder finden
                    $files = glob($pages_dir . 'page-*.jpg');
                    sort($files, SORT_NATURAL);
                    $images = $files;
                }
            }

            // Fallback: convert (ImageMagick)
            if (empty($images) && $this->command_exists('convert')) {
                error_log('ZK Vision: Verwende ImageMagick convert');

                for ($i = 0; $i < min($page_count, 20); $i++) {
                    $output_file = $pages_dir . 'page-' . sprintf('%03d', $i + 1) . '.jpg';
                    $command = sprintf(
                        'convert -density 150 %s[%d] -quality 85 %s 2>&1',
                        escapeshellarg($pdf_path),
                        $i,
                        escapeshellarg($output_file)
                    );
                    exec($command, $output, $return_var);

                    if ($return_var === 0 && file_exists($output_file)) {
                        $images[] = $output_file;
                    }
                }
            }

            error_log('ZK Vision: ' . count($images) . ' Seitenbilder erstellt');
            return $images;
        }

        /**
         * Prompt für Vision-Analyse erstellen
         */
        private function build_vision_analysis_prompt($page_count) {
            return <<<PROMPT
Du siehst die gescannten Seiten einer wissenschaftlichen Zeitschrift (Kardiotechnik).

Analysiere die Bilder und extrahiere:

1. **Ausgabe-Informationen** (von der Titelseite):
   - Jahr
   - Ausgabennummer
   - DOI (falls vorhanden)

2. **Alle Artikel** mit:
   - Titel
   - Autor(en)
   - Startseite
   - Endseite (schätzen wenn nicht klar)
   - Kategorie (Wissenschaftlicher Artikel, Übersichtsarbeit, Fallbericht, Editorial, Nachrichten, Kongress, Sonstiges)

Antworte im JSON-Format:
{
    "issue": {
        "jahr": 2024,
        "ausgabe": "1",
        "doi": "10.1234/zk.2024.1"
    },
    "articles": [
        {
            "titel": "Artikeltitel",
            "autoren": ["Autor 1", "Autor 2"],
            "start_seite": 1,
            "end_seite": 5,
            "kategorie": "Wissenschaftlicher Artikel"
        }
    ]
}

Wichtig:
- Lies den Text aus den Bildern sorgfältig
- Erkenne alle Artikel, auch kurze Nachrichten
- Seitenzahlen aus dem PDF (Seite 1-{$page_count})

Antworte NUR mit dem JSON-Objekt.
PROMPT;
        }

        /**
         * Claude Vision API aufrufen
         */
        private function call_anthropic_vision_api($content, $api_key) {
            $settings = get_option('zk_ai_settings', []);
            $model = $settings['model'] ?? 'claude-sonnet-4-20250514';

            // Vision funktioniert am besten mit Sonnet
            if (strpos($model, 'haiku') !== false) {
                $model = 'claude-sonnet-4-20250514';
            }

            error_log('=== ZK Claude Vision API ===');
            error_log('Modell: ' . $model);
            error_log('Content-Elemente: ' . count($content));

            $body_data = [
                'model' => $model,
                'max_tokens' => 16384,
                'messages' => [
                    ['role' => 'user', 'content' => $content]
                ],
            ];

            $json_body = json_encode($body_data, JSON_UNESCAPED_UNICODE);

            if ($json_body === false) {
                error_log('ZK Vision: JSON encode Fehler');
                return new WP_Error('json_error', 'JSON-Encoding fehlgeschlagen');
            }

            error_log('ZK Vision: Request Body Größe: ' . round(strlen($json_body) / 1024 / 1024, 2) . ' MB');

            $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
                'timeout' => 300, // 5 Minuten für Vision
                'headers' => [
                    'Content-Type' => 'application/json',
                    'x-api-key' => $api_key,
                    'anthropic-version' => '2023-06-01',
                ],
                'body' => $json_body,
            ]);

            if (is_wp_error($response)) {
                error_log('ZK Vision: WP Error - ' . $response->get_error_message());
                return $response;
            }

            $status = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            error_log('ZK Vision: HTTP Status ' . $status);

            if ($status !== 200) {
                $error = json_decode($body, true);
                $error_msg = $error['error']['message'] ?? 'API-Fehler: ' . $status;
                error_log('ZK Vision: API Fehler - ' . $error_msg);
                return new WP_Error('api_error', $error_msg);
            }

            $data = json_decode($body, true);

            if (!isset($data['content'][0]['text'])) {
                error_log('ZK Vision: Ungültige Antwortstruktur');
                return new WP_Error('parse_error', 'Ungültige API-Antwort');
            }

            $result_text = $data['content'][0]['text'];
            error_log('ZK Vision: Antwort erhalten (' . strlen($result_text) . ' Zeichen)');

            // JSON aus Antwort extrahieren
            $result = json_decode($result_text, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                // Versuche JSON aus Markdown-Block zu extrahieren
                if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $result_text, $matches)) {
                    $result = json_decode($matches[1], true);
                }
            }

            if (!$result) {
                error_log('ZK Vision: JSON Parse Fehler - ' . json_last_error_msg());
                error_log('ZK Vision: Antwort-Text: ' . substr($result_text, 0, 500));
                return new WP_Error('parse_error', 'Konnte JSON nicht parsen');
            }

            error_log('ZK Vision: Erfolgreich! ' . count($result['articles'] ?? []) . ' Artikel erkannt');

            return $result;
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
                error_log('ZK AI: Modell ' . $model . ' veraltet, verwende ' . $deprecated_models[$model]);
                $model = $deprecated_models[$model];
            }

            error_log('=== ZK Anthropic API Aufruf ===');
            error_log('Modell: ' . $model);
            error_log('API-Key vorhanden: ' . (!empty($api_key) ? 'ja (Länge: ' . strlen($api_key) . ')' : 'NEIN!'));
            error_log('Prompt-Länge: ' . strlen($prompt) . ' Zeichen');

            // Prompt bereinigen für gültiges UTF-8 (wichtig für json_encode)
            $prompt = $this->sanitize_utf8($prompt);
            error_log('Prompt nach UTF-8 Bereinigung: ' . strlen($prompt) . ' Zeichen');

            $body_data = [
                'model' => $model,
                'max_tokens' => 16384,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
            ];

            $json_body = json_encode($body_data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

            if ($json_body === false) {
                error_log('ZK AI: json_encode FEHLER - ' . json_last_error_msg());
                return new WP_Error('json_error', 'JSON-Encoding fehlgeschlagen: ' . json_last_error_msg());
            }

            error_log('JSON Body Länge: ' . strlen($json_body) . ' Bytes');

            $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
                'timeout' => 180,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'x-api-key' => $api_key,
                    'anthropic-version' => '2023-06-01',
                ],
                'body' => $json_body,
            ]);

            if (is_wp_error($response)) {
                error_log('ZK AI: WP Error - ' . $response->get_error_message());
                return $response;
            }

            $status = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            error_log('ZK AI: HTTP Status ' . $status);

            if ($status !== 200) {
                $error = json_decode($body, true);
                $error_msg = $error['error']['message'] ?? 'API-Fehler: ' . $status;
                error_log('ZK AI: API Fehler - ' . $error_msg);
                error_log('ZK AI: Response Body - ' . substr($body, 0, 500));
                return new WP_Error('api_error', $error_msg . ' (Modell: ' . $model . ')');
            }

            $data = json_decode($body, true);

            if (!isset($data['content'][0]['text'])) {
                error_log('ZK AI: Ungültige Antwortstruktur - ' . substr($body, 0, 500));
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
            // API-Key: Nur trimmen, nicht sanitize_text_field (kann Bindestriche entfernen)
            $api_key = isset($_POST['api_key']) ? trim(wp_unslash($_POST['api_key'])) : '';
            $model = sanitize_text_field($_POST['model'] ?? 'claude-sonnet-4-20250514');

            error_log('=== ZK AI Settings SAVE ===');
            error_log('POST api_key raw: ' . (isset($_POST['api_key']) ? 'vorhanden, Länge: ' . strlen($_POST['api_key']) : 'NICHT VORHANDEN'));
            error_log('api_key nach trim: ' . (!empty($api_key) ? 'Länge: ' . strlen($api_key) . ', Start: ' . substr($api_key, 0, 10) : 'LEER'));

            // Alte Settings laden
            $old_settings = get_option('zk_ai_settings', []);
            error_log('Alte Settings: ' . print_r(array_keys($old_settings), true));
            error_log('Alter API-Key existiert: ' . (!empty($old_settings['api_key']) ? 'ja' : 'nein'));

            // Neue Settings erstellen
            $settings = [
                'provider' => $provider,
                'model' => $model,
                'api_key' => '', // Wird unten gesetzt
            ];

            // API-Key: Neuen verwenden oder alten beibehalten
            if (!empty($api_key) && strpos($api_key, '...') === false) {
                $settings['api_key'] = $api_key;
                error_log('ZK AI Settings: NEUER API-Key wird gespeichert (Länge: ' . strlen($api_key) . ')');
            } elseif (!empty($old_settings['api_key'])) {
                $settings['api_key'] = $old_settings['api_key'];
                error_log('ZK AI Settings: ALTER API-Key beibehalten (Länge: ' . strlen($old_settings['api_key']) . ')');
            } else {
                error_log('ZK AI Settings: KEIN API-Key vorhanden!');
            }

            // Option löschen und neu erstellen für sauberes Speichern
            delete_option('zk_ai_settings');
            $result = add_option('zk_ai_settings', $settings, '', 'yes');

            error_log('ZK AI Settings: add_option Ergebnis: ' . ($result ? 'SUCCESS' : 'FAILED'));

            // Verifizieren
            $verify = get_option('zk_ai_settings', []);
            $verified_key = !empty($verify['api_key']);
            error_log('ZK AI Settings: Verifizierung - API-Key gespeichert: ' . ($verified_key ? 'JA (Länge: ' . strlen($verify['api_key']) . ')' : 'NEIN!'));

            wp_send_json_success([
                'message' => $verified_key ? 'Einstellungen gespeichert' : 'Fehler beim Speichern des API-Keys!',
                'has_key' => $verified_key,
                'saved' => $result,
                'verified' => $verified_key,
            ]);
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
