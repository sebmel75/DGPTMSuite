<?php
/**
 * Survey Exporter - CSV export of results
 */

if (!defined('ABSPATH')) {
    exit;
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

        if (!current_user_can('manage_options')) {
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
        $question_columns = []; // Maps column index to question info
        foreach ($questions as $q) {
            if ($q->question_type === 'matrix') {
                $choices = json_decode($q->choices, true);
                if (isset($choices['rows'])) {
                    foreach ($choices['rows'] as $row) {
                        $headers[] = $q->question_text . ' - ' . $row;
                        $question_columns[] = ['question' => $q, 'matrix_row' => sanitize_title($row)];
                    }
                }
            } else {
                $headers[] = $q->question_text;
                $question_columns[] = ['question' => $q, 'matrix_row' => null];
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

                if (!$answer) {
                    $row[] = '';
                    continue;
                }

                if ($q->question_type === 'matrix' && $col['matrix_row']) {
                    $vals = json_decode($answer->answer_value, true);
                    $row[] = is_array($vals) && isset($vals[$col['matrix_row']]) ? $vals[$col['matrix_row']] : '';
                } elseif ($q->question_type === 'checkbox') {
                    $vals = json_decode($answer->answer_value, true);
                    $row[] = is_array($vals) ? implode('; ', $vals) : ($answer->answer_value ?: '');
                } elseif ($q->question_type === 'file') {
                    if ($answer->file_ids) {
                        $fids = json_decode($answer->file_ids, true);
                        $urls = [];
                        if (is_array($fids)) {
                            foreach ($fids as $fid) {
                                $url = wp_get_attachment_url(absint($fid));
                                if ($url) {
                                    $urls[] = $url;
                                }
                            }
                        }
                        $row[] = implode('; ', $urls);
                    } else {
                        $row[] = '';
                    }
                } else {
                    $row[] = $answer->answer_value ?: '';
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
}
