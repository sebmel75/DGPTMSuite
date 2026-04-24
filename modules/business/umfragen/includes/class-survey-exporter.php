<?php
/**
 * Survey Exporter - CSV export of results
 */

if (!defined('ABSPATH')) {
    exit;
}

// FPDF subclass must be declared at file level (not nested inside another class)
// Loaded lazily when FPDF is available
if (file_exists(DGPTM_SUITE_PATH . 'libraries/fpdf/fpdf.php')) {
    require_once DGPTM_SUITE_PATH . 'libraries/fpdf/fpdf.php';
    if (class_exists('FPDF') && !class_exists('DGPTM_Survey_FPDF')) {
        class DGPTM_Survey_FPDF extends FPDF {
            public function Header() {
                // Empty - title is added manually on title page
            }

            public function Footer() {
                $this->SetY(-15);
                $this->SetFont('Arial', 'I', 8);
                $this->Cell(0, 10, 'Seite ' . $this->PageNo() . ' / {nb}', 0, 0, 'C');
            }
        }
    }
}

class DGPTM_Survey_Exporter {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    /**
     * Parst einen gespeicherten Antwortwert im Format "base|||text|||number".
     * Fehlende Bestandteile werden als leere Strings zurueckgegeben.
     */
    private function parse_value($raw) {
        if (!is_string($raw)) {
            return ['base' => '', 'text' => '', 'number' => ''];
        }
        $parts = explode('|||', $raw, 3);
        return [
            'base'   => $parts[0] ?? '',
            'text'   => $parts[1] ?? '',
            'number' => $parts[2] ?? '',
        ];
    }

    /**
     * Normalisiert einen Choice-Basiswert fuer die Anzeige.
     * __other__ -> "Sonstiges"; sonst Rohwert.
     */
    private function display_label($base) {
        return $base === '__other__' ? 'Sonstiges' : $base;
    }

    /**
     * Liest die Antwort einer radio/select/checkbox-Frage in eine einheitliche
     * Liste geparster Items auf (auch bei Einzelantworten).
     *
     * @return array<int, array{base:string,text:string,number:string}>
     */
    private function read_choice_items($question, $answer) {
        if (!$answer || $answer->answer_value === null) {
            return [];
        }
        $raw = $answer->answer_value;
        if ($question->question_type === 'checkbox') {
            $arr = json_decode($raw, true);
            if (!is_array($arr)) {
                return [];
            }
            $out = [];
            foreach ($arr as $v) {
                $out[] = $this->parse_value((string) $v);
            }
            return $out;
        }
        return [$this->parse_value((string) $raw)];
    }

    /**
     * Sucht in einer Item-Liste das Item mit passender Choice-Base (inkl. __other__).
     */
    private function find_choice_item($items, $target_base) {
        foreach ($items as $item) {
            if ($item['base'] === $target_base) {
                return $item;
            }
        }
        return null;
    }

    /**
     * Export survey results as CSV download
     */
    public function export_csv() {
        // Check via GET for direct link, or POST for AJAX
        if (!isset($_GET['nonce']) && !isset($_POST['nonce'])) {
            wp_die('Fehlender Nonce');
        }

        $nonce = isset($_GET['nonce']) ? $_GET['nonce'] : $_POST['nonce'];
        if (!wp_verify_nonce($nonce, 'dgptm_suite_nonce')) {
            wp_die('Ungueltiger Nonce');
        }

        if (!DGPTM_Umfragen::user_can_manage_surveys()) {
            wp_die('Keine Berechtigung');
        }

        $survey_id = absint(isset($_GET['survey_id']) ? $_GET['survey_id'] : ($_POST['survey_id'] ?? 0));
        if (!$survey_id) {
            wp_die('Keine Umfrage-ID');
        }

        global $wpdb;

        // Get survey
        $survey = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dgptm_surveys WHERE id = %d",
            $survey_id
        ));

        if (!$survey) {
            wp_die('Umfrage nicht gefunden');
        }

        // Ownership check for non-admins
        if (!current_user_can('manage_options')) {
            $uid = get_current_user_id();
            if ((int) $survey->created_by !== $uid && !DGPTM_Survey_Frontend_Editor::is_shared_with($survey, $uid)) {
                wp_die('Keine Berechtigung fuer diese Umfrage');
            }
        }

        // Get questions
        $questions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dgptm_survey_questions WHERE survey_id = %d ORDER BY sort_order ASC",
            $survey_id
        ));

        // Get completed responses
        $responses = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dgptm_survey_responses WHERE survey_id = %d AND status = 'completed' ORDER BY completed_at ASC",
            $survey_id
        ));

        // Build CSV headers
        $headers = ['Antwort-ID', 'Name', 'E-Mail', 'IP', 'Datum'];

        // Question columns
        // Mode-Uebersicht:
        //   matrix        : bestehendes Verhalten (eine Spalte pro Zeile bzw. Zelle)
        //   simple        : Eine Spalte mit Rohwert (number, text, textarea, file, ohne |||-Features)
        //   choice_plain  : Eine Spalte mit gewaehlten Label(s), fuer radio/select/checkbox ohne Features
        //   choice_number : Eine Spalte pro Choice bei choices_with_number, Wert = Zahl
        //   choice_text   : Zusatzspalte pro Choice mit choices_with_text, Wert = Freitext
        //   other_text    : Zusatzspalte fuer __other__-Freitext (Sonstiges)
        $question_columns = [];
        foreach ($questions as $q) {
            if ($q->question_type === 'matrix') {
                $choices = json_decode($q->choices, true);
                $matrix_type = isset($choices['matrix_input_type']) ? $choices['matrix_input_type'] : 'radio';
                if ($matrix_type === 'number' && isset($choices['rows']) && isset($choices['columns'])) {
                    foreach ($choices['rows'] as $row) {
                        foreach ($choices['columns'] as $col) {
                            $headers[] = $q->question_text . ' - ' . $row . ' / ' . $col;
                            $question_columns[] = ['question' => $q, 'mode' => 'matrix', 'matrix_row' => sanitize_title($row), 'matrix_col' => sanitize_title($col), 'matrix_type' => 'number'];
                        }
                    }
                } elseif (isset($choices['rows'])) {
                    foreach ($choices['rows'] as $row) {
                        $headers[] = $q->question_text . ' - ' . $row;
                        $question_columns[] = ['question' => $q, 'mode' => 'matrix', 'matrix_row' => sanitize_title($row), 'matrix_col' => null, 'matrix_type' => 'radio'];
                    }
                }
            } elseif (in_array($q->question_type, ['radio', 'select', 'checkbox'], true)) {
                $choices    = $q->choices ? json_decode($q->choices, true) : [];
                $validation = $q->validation_rules ? json_decode($q->validation_rules, true) : [];
                if (!is_array($choices))    $choices = [];
                if (!is_array($validation)) $validation = [];

                $cwt = isset($validation['choices_with_text']) ? (array) $validation['choices_with_text'] : [];
                $cwn_raw = isset($validation['choices_with_number']) ? (array) $validation['choices_with_number'] : [];
                $cwn_choices = [];
                foreach ($cwn_raw as $cn) {
                    if (is_array($cn) && isset($cn['choice'])) {
                        $cwn_choices[] = $cn['choice'];
                    }
                }
                $allow_other = !empty($validation['allow_other']);

                if (!empty($cwn_choices)) {
                    // Choices_with_number: eine Spalte pro Choice, Wert = Zahl (oder leer)
                    foreach ($choices as $choice) {
                        $headers[] = $q->question_text . ' — ' . $choice;
                        $question_columns[] = ['question' => $q, 'mode' => 'choice_number', 'choice' => $choice];
                    }
                    if ($allow_other) {
                        $headers[] = $q->question_text . ' — Sonstiges — Freitext';
                        $question_columns[] = ['question' => $q, 'mode' => 'other_text'];
                    }
                } else {
                    // Hauptspalte mit Label(s)
                    $headers[] = $q->question_text;
                    $question_columns[] = ['question' => $q, 'mode' => 'choice_plain'];

                    // Zusatzspalten fuer Choices mit Freitext
                    foreach ($choices as $choice) {
                        if (in_array($choice, $cwt, true)) {
                            $headers[] = $q->question_text . ' — ' . $choice . ' — Freitext';
                            $question_columns[] = ['question' => $q, 'mode' => 'choice_text', 'choice' => $choice];
                        }
                    }
                    if ($allow_other) {
                        $headers[] = $q->question_text . ' — Sonstiges — Freitext';
                        $question_columns[] = ['question' => $q, 'mode' => 'other_text'];
                    }
                }
            } else {
                // number, text, textarea, file
                $headers[] = $q->question_text;
                $question_columns[] = ['question' => $q, 'mode' => 'simple'];
            }
        }

        // Build CSV rows
        $rows = [];
        foreach ($responses as $response) {
            $answers = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}dgptm_survey_answers WHERE response_id = %d",
                $response->id
            ));

            $answers_map = [];
            foreach ($answers as $a) {
                $answers_map[$a->question_id] = $a;
            }

            $row = [
                $response->id,
                $response->respondent_name,
                $response->respondent_email,
                $response->respondent_ip,
                wp_date('d.m.Y H:i', strtotime($response->completed_at)),
            ];

            foreach ($question_columns as $col) {
                $q = $col['question'];
                $answer = isset($answers_map[$q->id]) ? $answers_map[$q->id] : null;
                $mode = $col['mode'] ?? 'simple';

                if (!$answer) {
                    $row[] = '';
                    continue;
                }

                switch ($mode) {
                    case 'matrix': {
                        $vals = json_decode($answer->answer_value, true);
                        if (!empty($col['matrix_col']) && ($col['matrix_type'] ?? '') === 'number') {
                            $row[] = is_array($vals) && isset($vals[$col['matrix_row']][$col['matrix_col']])
                                ? $vals[$col['matrix_row']][$col['matrix_col']]
                                : '';
                        } else {
                            $row[] = is_array($vals) && isset($vals[$col['matrix_row']])
                                ? $vals[$col['matrix_row']]
                                : '';
                        }
                        break;
                    }

                    case 'choice_number': {
                        $items = $this->read_choice_items($q, $answer);
                        $found = $this->find_choice_item($items, $col['choice']);
                        $row[] = $found ? $found['number'] : '';
                        break;
                    }

                    case 'choice_text': {
                        $items = $this->read_choice_items($q, $answer);
                        $found = $this->find_choice_item($items, $col['choice']);
                        $row[] = $found ? $found['text'] : '';
                        break;
                    }

                    case 'other_text': {
                        $items = $this->read_choice_items($q, $answer);
                        $found = $this->find_choice_item($items, '__other__');
                        $row[] = $found ? $found['text'] : '';
                        break;
                    }

                    case 'choice_plain': {
                        $items = $this->read_choice_items($q, $answer);
                        $labels = [];
                        foreach ($items as $item) {
                            if ($item['base'] === '') continue;
                            $labels[] = $this->display_label($item['base']);
                        }
                        $row[] = implode('; ', $labels);
                        break;
                    }

                    case 'simple':
                    default: {
                        if ($q->question_type === 'file') {
                            $urls = [];
                            if ($answer->file_ids) {
                                $fids = json_decode($answer->file_ids, true);
                                if (is_array($fids)) {
                                    foreach ($fids as $fid) {
                                        $url = wp_get_attachment_url(absint($fid));
                                        if ($url) $urls[] = $url;
                                    }
                                }
                            }
                            $row[] = implode('; ', $urls);
                        } else {
                            $row[] = $answer->answer_value ?: '';
                        }
                        break;
                    }
                }
            }

            $rows[] = $row;
        }

        // Output CSV
        $filename = sanitize_file_name($survey->slug . '-export-' . wp_date('Y-m-d') . '.csv');

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // BOM for Excel UTF-8 compatibility
        fwrite($output, "\xEF\xBB\xBF");

        fputcsv($output, $headers, ';');

        foreach ($rows as $row) {
            fputcsv($output, $row, ';');
        }

        fclose($output);

        if (function_exists('dgptm_log_info')) {
            dgptm_log_info('CSV-Export fuer Umfrage "' . $survey->title . '" (' . count($rows) . ' Zeilen)', 'umfragen');
        }

        exit;
    }

    /**
     * Export survey results as PDF download
     */
    public function export_pdf() {
        // Check via GET for direct link, or POST for AJAX
        if (!isset($_GET['nonce']) && !isset($_POST['nonce'])) {
            wp_die('Fehlender Nonce');
        }

        $nonce = isset($_GET['nonce']) ? $_GET['nonce'] : $_POST['nonce'];
        if (!wp_verify_nonce($nonce, 'dgptm_suite_nonce')) {
            wp_die('Ungueltiger Nonce');
        }

        if (!DGPTM_Umfragen::user_can_manage_surveys()) {
            wp_die('Keine Berechtigung');
        }

        $survey_id = absint(isset($_GET['survey_id']) ? $_GET['survey_id'] : ($_POST['survey_id'] ?? 0));
        if (!$survey_id) {
            wp_die('Keine Umfrage-ID');
        }

        global $wpdb;

        // Get survey
        $survey = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dgptm_surveys WHERE id = %d",
            $survey_id
        ));

        if (!$survey) {
            wp_die('Umfrage nicht gefunden');
        }

        // Ownership check for non-admins
        if (!current_user_can('manage_options')) {
            $uid = get_current_user_id();
            if ((int) $survey->created_by !== $uid && !DGPTM_Survey_Frontend_Editor::is_shared_with($survey, $uid)) {
                wp_die('Keine Berechtigung fuer diese Umfrage');
            }
        }

        // Get questions
        $questions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dgptm_survey_questions WHERE survey_id = %d ORDER BY sort_order ASC",
            $survey_id
        ));

        // Get completed responses
        $responses = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dgptm_survey_responses WHERE survey_id = %d AND status = 'completed' ORDER BY completed_at ASC",
            $survey_id
        ));

        if (!class_exists('DGPTM_Survey_FPDF')) {
            wp_die('FPDF-Klasse nicht verfuegbar.');
        }

        $pdf = new DGPTM_Survey_FPDF('P', 'mm', 'A4');
        $pdf->AliasNbPages();
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 15);

        // ── Title page ──────────────────────────────────────────────
        $pdf->AddPage();

        $pdf->Ln(30);

        // Survey title
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->MultiCell(0, 8, utf8_decode($survey->title), 0, 'C');
        $pdf->Ln(6);

        // Description
        if (!empty($survey->description)) {
            $pdf->SetFont('Arial', '', 11);
            $pdf->MultiCell(0, 6, utf8_decode($survey->description), 0, 'C');
            $pdf->Ln(6);
        }

        // Separator line
        $pdf->SetDrawColor(180, 180, 180);
        $pdf->Line(30, $pdf->GetY(), 180, $pdf->GetY());
        $pdf->Ln(8);

        // Response count
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 7, utf8_decode('Anzahl abgeschlossener Antworten: ' . count($responses)), 0, 1, 'C');
        $pdf->Ln(2);

        // Date range
        if (!empty($responses)) {
            // Responses are ordered ASC, so first element = earliest, last element = latest
            $first = reset($responses);
            $last  = end($responses);

            $date_from = wp_date('d.m.Y', strtotime($first->completed_at));
            $date_to   = wp_date('d.m.Y', strtotime($last->completed_at));
            $pdf->Cell(0, 7, utf8_decode('Zeitraum: ' . $date_from . ' - ' . $date_to), 0, 1, 'C');
        }

        $pdf->Ln(4);

        // Export date
        $pdf->SetFont('Arial', 'I', 9);
        $pdf->Cell(0, 7, utf8_decode('Exportiert am ' . wp_date('d.m.Y H:i')), 0, 1, 'C');

        // Get admin instance for answer queries
        $admin = DGPTM_Survey_Admin::get_instance();

        // ── Per-question results ────────────────────────────────────
        $question_number = 0;
        foreach ($questions as $q) {
            $question_number++;
            $q_answers = $admin->get_question_answers($q->id, $survey_id);
            $choices   = $q->choices ? json_decode($q->choices, true) : [];

            // Start new page for each question for clarity
            $pdf->AddPage();

            // Question header
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->MultiCell(0, 7, utf8_decode($question_number . '. ' . $q->question_text));
            $pdf->Ln(1);

            // Answer count
            $pdf->SetFont('Arial', 'I', 9);
            $pdf->Cell(0, 5, utf8_decode(count($q_answers) . ' Antwort(en)'), 0, 1);
            $pdf->Ln(3);

            $pdf->SetFont('Arial', '', 10);

            switch ($q->question_type) {

                case 'radio':
                case 'select':
                    // Count per choice
                    $counts = [];
                    if (is_array($choices)) {
                        foreach ($choices as $c) {
                            $counts[$c] = 0;
                        }
                    }
                    foreach ($q_answers as $a) {
                        $v = $a->answer_value;
                        if (isset($counts[$v])) {
                            $counts[$v]++;
                        } else {
                            $counts[$v] = 1;
                        }
                    }
                    $total = count($q_answers);

                    foreach ($counts as $label => $count) {
                        $pct = $total > 0 ? round(($count / $total) * 100, 1) : 0;
                        $line = $label . ':  ' . $count . '  (' . $pct . '%)';
                        $pdf->Cell(6, 6, chr(149), 0, 0); // bullet
                        $pdf->MultiCell(0, 6, utf8_decode($line));
                    }
                    break;

                case 'checkbox':
                    // Count per choice (multiple selections)
                    $counts = [];
                    if (is_array($choices)) {
                        foreach ($choices as $c) {
                            $counts[$c] = 0;
                        }
                    }
                    foreach ($q_answers as $a) {
                        $vals = json_decode($a->answer_value, true);
                        if (is_array($vals)) {
                            foreach ($vals as $v) {
                                if (isset($counts[$v])) {
                                    $counts[$v]++;
                                } else {
                                    $counts[$v] = 1;
                                }
                            }
                        }
                    }
                    $total = count($q_answers);

                    foreach ($counts as $label => $count) {
                        $pct = $total > 0 ? round(($count / $total) * 100, 1) : 0;
                        $line = $label . ':  ' . $count . '  (' . $pct . '%)';
                        $pdf->Cell(6, 6, chr(149), 0, 0); // bullet
                        $pdf->MultiCell(0, 6, utf8_decode($line));
                    }
                    break;

                case 'number':
                    // Statistical summary
                    $values = [];
                    foreach ($q_answers as $a) {
                        if (is_numeric($a->answer_value)) {
                            $values[] = floatval($a->answer_value);
                        }
                    }

                    if (!empty($values)) {
                        sort($values);
                        $cnt        = count($values);
                        $sum        = array_sum($values);
                        $avg        = $sum / $cnt;
                        $median_idx = (int) floor($cnt / 2);
                        $median     = $cnt % 2 === 0
                            ? ($values[$median_idx - 1] + $values[$median_idx]) / 2
                            : $values[$median_idx];

                        $stats = [
                            'Minimum'       => number_format_i18n(min($values), 1),
                            'Maximum'       => number_format_i18n(max($values), 1),
                            'Durchschnitt'  => number_format_i18n($avg, 1),
                            'Median'        => number_format_i18n($median, 1),
                        ];

                        foreach ($stats as $stat_label => $stat_value) {
                            $pdf->SetFont('Arial', 'B', 10);
                            $pdf->Cell(40, 6, utf8_decode($stat_label . ':'), 0, 0);
                            $pdf->SetFont('Arial', '', 10);
                            $pdf->Cell(0, 6, utf8_decode($stat_value), 0, 1);
                        }
                    } else {
                        $pdf->SetFont('Arial', 'I', 10);
                        $pdf->Cell(0, 6, utf8_decode('Keine numerischen Antworten.'), 0, 1);
                    }
                    break;

                case 'matrix':
                    // Simple text table
                    $rows = isset($choices['rows']) ? $choices['rows'] : [];
                    $cols = isset($choices['columns']) ? $choices['columns'] : [];

                    if (empty($rows) || empty($cols)) {
                        $pdf->SetFont('Arial', 'I', 10);
                        $pdf->Cell(0, 6, utf8_decode('Keine Matrix-Daten vorhanden.'), 0, 1);
                        break;
                    }

                    // Build counts
                    $matrix_counts = [];
                    foreach ($rows as $r) {
                        $rk = sanitize_title($r);
                        foreach ($cols as $c) {
                            $matrix_counts[$rk][$c] = 0;
                        }
                    }
                    foreach ($q_answers as $a) {
                        $vals = json_decode($a->answer_value, true);
                        if (is_array($vals)) {
                            foreach ($vals as $rk => $cv) {
                                if (isset($matrix_counts[$rk][$cv])) {
                                    $matrix_counts[$rk][$cv]++;
                                }
                            }
                        }
                    }

                    // Calculate column widths
                    $num_cols   = count($cols);
                    $row_label_w = 50; // mm for row labels
                    $available_w = 190 - $row_label_w; // total printable width minus row label
                    $col_w       = $num_cols > 0 ? min(30, $available_w / $num_cols) : 30;

                    // Header row
                    $pdf->SetFont('Arial', 'B', 8);
                    $pdf->Cell($row_label_w, 6, '', 1, 0, 'C'); // empty top-left
                    foreach ($cols as $c) {
                        $pdf->Cell($col_w, 6, utf8_decode($this->truncate_text($c, 18)), 1, 0, 'C');
                    }
                    $pdf->Ln();

                    // Data rows
                    $pdf->SetFont('Arial', '', 8);
                    foreach ($rows as $r) {
                        $rk = sanitize_title($r);

                        // Check page break
                        if ($pdf->GetY() + 6 > 280) {
                            $pdf->AddPage();
                            // Re-print header
                            $pdf->SetFont('Arial', 'B', 8);
                            $pdf->Cell($row_label_w, 6, '', 1, 0, 'C');
                            foreach ($cols as $c) {
                                $pdf->Cell($col_w, 6, utf8_decode($this->truncate_text($c, 18)), 1, 0, 'C');
                            }
                            $pdf->Ln();
                            $pdf->SetFont('Arial', '', 8);
                        }

                        $pdf->SetFont('Arial', 'B', 8);
                        $pdf->Cell($row_label_w, 6, utf8_decode($this->truncate_text($r, 30)), 1, 0, 'L');
                        $pdf->SetFont('Arial', '', 8);
                        foreach ($cols as $c) {
                            $val = isset($matrix_counts[$rk][$c]) ? $matrix_counts[$rk][$c] : 0;
                            $pdf->Cell($col_w, 6, $val, 1, 0, 'C');
                        }
                        $pdf->Ln();
                    }
                    break;

                case 'text':
                case 'textarea':
                    // List responses (max 20)
                    if (empty($q_answers)) {
                        $pdf->SetFont('Arial', 'I', 10);
                        $pdf->Cell(0, 6, utf8_decode('Keine Antworten.'), 0, 1);
                        break;
                    }

                    $shown = 0;
                    foreach ($q_answers as $a) {
                        if ($shown >= 20) {
                            break;
                        }
                        $text = $a->answer_value;
                        if (empty($text)) {
                            continue;
                        }

                        // Truncate very long responses
                        if (mb_strlen($text) > 300) {
                            $text = mb_substr($text, 0, 300) . '...';
                        }

                        // Check page break
                        if ($pdf->GetY() + 10 > 280) {
                            $pdf->AddPage();
                        }

                        $pdf->SetFont('Arial', '', 9);
                        $pdf->SetFillColor(245, 245, 245);
                        $x_before = $pdf->GetX();
                        $y_before = $pdf->GetY();
                        $pdf->MultiCell(190, 5, utf8_decode($text), 0, 'L', true);
                        $pdf->Ln(2);

                        $shown++;
                    }

                    if (count($q_answers) > 20) {
                        $pdf->SetFont('Arial', 'I', 9);
                        $pdf->Cell(0, 6, utf8_decode('... und ' . (count($q_answers) - 20) . ' weitere Antwort(en)'), 0, 1);
                    }
                    break;

                case 'file':
                    // Just count files
                    $file_count = 0;
                    foreach ($q_answers as $a) {
                        if ($a->file_ids) {
                            $fids = json_decode($a->file_ids, true);
                            if (is_array($fids)) {
                                $file_count += count($fids);
                            }
                        }
                    }
                    $pdf->Cell(0, 6, utf8_decode($file_count . ' Datei(en) hochgeladen.'), 0, 1);
                    break;
            }
        }

        // Output PDF as download
        $filename = sanitize_file_name($survey->slug . '-export-' . wp_date('Y-m-d') . '.pdf');

        if (function_exists('dgptm_log_info')) {
            dgptm_log_info('PDF-Export fuer Umfrage "' . $survey->title . '" (' . count($responses) . ' Antworten)', 'umfragen');
        }

        $pdf->Output('D', $filename);
        exit;
    }

    /**
     * Truncate text to max length for PDF table cells
     */
    private function truncate_text($text, $max_len = 30) {
        if (mb_strlen($text) > $max_len) {
            return mb_substr($text, 0, $max_len - 1) . '.';
        }
        return $text;
    }
}
