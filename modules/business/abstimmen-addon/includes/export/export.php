<?php
// File: includes/export/export.php

if (!defined('ABSPATH')) exit;

add_action('wp_ajax_dgptm_export_poll_complete','dgptm_export_poll_complete_fn');
function dgptm_export_poll_complete_fn(){
    if(!dgptm_is_manager()) wp_die('Nicht autorisiert.');
    if(empty($_GET['poll_id'])) wp_die('Keine poll_id.');
    if(empty($_GET['format'])) wp_die('Kein format.');
    global $wpdb;
    $pid=intval($_GET['poll_id']);
    $format=sanitize_text_field($_GET['format']);

    $poll=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_polls WHERE id=%d",$pid));
    if(!$poll) wp_die('Umfrage nicht gefunden.');

    if($format==='csv'){
        header('Content-Type: text/csv; charset='.get_option('blog_charset'));
        header('Content-Disposition: attachment; filename="poll_'.$pid.'_complete.csv"');
        $out=fopen('php://output','w');
        fputcsv($out,array('Umfrage',$poll->name),';');
        fputcsv($out,array('ID',$poll->id),';');
        fputcsv($out,array('Status',$poll->status),';');
        fputcsv($out,array('Erstellt',$poll->created),';');
        fputcsv($out,array('---'),';');

        $questions=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_poll_questions WHERE poll_id=%d ORDER BY created ASC",$pid));
        if($questions){
            foreach($questions as $q){
                fputcsv($out,array('Frage-ID',$q->id,'Text',$q->question),';');
                fputcsv($out,array('Status',$q->status,'Ergebnis freigegeben',$q->results_released,'In Gesamtstatistik',$q->in_overall),';');
                $vc = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}dgptm_abstimmung_votes WHERE question_id=%d AND is_invalid=0",$q->id));
                fputcsv($out,array('Stimmen (gültig)',$vc),';');
                if($q->ended && strtotime($q->ended)>strtotime($q->created)){
                    $dur=strtotime($q->ended)-strtotime($q->created);
                    fputcsv($out,array('Dauer in Sek.',$dur),';');
                }
                fputcsv($out,array('Anonym?',$q->is_anonymous),';');
                fputcsv($out,array('Diagramm',$q->chart_type),';');
                fputcsv($out,array('---'),';');
            }
        }
        fputcsv($out,array('---'),';');
        fputcsv($out,array('Teilnehmer-Liste'),';');

        $rows=$wpdb->get_results($wpdb->prepare("
          SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_participants
          WHERE poll_id=%d
          ORDER BY joined_time ASC
        ",$pid));
        fputcsv($out,array('UserID','Vollname','Vorname','Nachname','MitgliedsNr','Status','E-Mail','Cookie-ID','Token','Quelle','Zeit'),';');
        foreach($rows as $r){
            fputcsv($out,array($r->user_id,$r->fullname,$r->first_name,$r->last_name,$r->member_no,$r->member_status,$r->email,$r->cookie_id,$r->token,$r->source,$r->joined_time),';');
        }
        fclose($out);
        exit;
    } elseif($format==='pdf'){
        $fpdf_path = __DIR__.'/../vendor/fpdf/fpdf.php';
        if(!file_exists($fpdf_path)){
            wp_die('FPDF nicht gefunden (erwartet: includes/vendor/fpdf/fpdf.php).');
        }

        require_once $fpdf_path;
        if(!class_exists('FPDF')) wp_die('FPDF-Klasse nicht verfügbar.');
        if(!class_exists('DGPTM_FPDF')){ class DGPTM_FPDF extends FPDF{} }

        $pdf = new DGPTM_FPDF();
        $pdf->AddPage(); $pdf->SetFont('Arial','B',14);
        $pdf->Cell(0,8,utf8_decode("Umfrage: {$poll->name} (ID: {$poll->id})"),0,1);
        $pdf->SetFont('Arial','',11);
        $pdf->Cell(0,6,utf8_decode("Status: {$poll->status}  |  Erstellt: {$poll->created}"),0,1);
        $pdf->Ln(2);

        $questions=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_poll_questions WHERE poll_id=%d ORDER BY created ASC",$pid));
        if($questions){
            foreach($questions as $q){
                $pdf->SetFont('Arial','B',12);
                $pdf->Cell(0,7,utf8_decode("Frage #{$q->id}: {$q->question}"),0,1);
                $pdf->SetFont('Arial','',11);
                $vc=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}dgptm_abstimmung_votes WHERE question_id=%d AND is_invalid=0",$q->id));
                $pdf->Cell(0,6,utf8_decode("Status: {$q->status} | Ergebnis freigegeben: ".($q->results_released?'Ja':'Nein')." | In Gesamtstatistik: ".($q->in_overall?'Ja':'Nein')." | Stimmen (gültig): ".$vc." | Diagramm: ".($q->chart_type ? $q->chart_type : 'bar')),0,1);
                if($q->ended && strtotime($q->ended)>strtotime($q->created)){
                    $dur=strtotime($q->ended)-strtotime($q->created);
                    $pdf->Cell(0,6,utf8_decode("Dauer: {$dur} Sekunden"),0,1);
                }
                $pdf->Ln(2);
            }
        }

        $pdf->Ln(3);
        $pdf->SetFont('Arial','B',12);
        $pdf->Cell(0,7,utf8_decode("Registrierte Teilnehmer"),0,1);
        $pdf->SetFont('Arial','',10);
        $rows=$wpdb->get_results($wpdb->prepare("
          SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_participants
          WHERE poll_id=%d
          ORDER BY joined_time ASC
        ",$pid));
        if(!$rows){
            $pdf->Cell(0,6,utf8_decode("Keine Teilnehmer."),0,1);
        } else {
            foreach($rows as $r){
                $line = "Name: ".($r->first_name.' '.$r->last_name);
                if(trim($line)==='Name: ') $line = "Vollname: ".$r->fullname;
                $line .= " | MitgliedsNr: ".$r->member_no." | Status: ".$r->member_status." | Zeit: ".$r->joined_time;
                $pdf->Cell(0,6,utf8_decode($line),0,1);
            }
        }
        $pdf->Output('I','poll_'.$pid.'_complete.pdf');
        exit;
    } else {
        wp_die('Unbekanntes Format.');
    }
}

/**
 * Wahlprotokoll als PDF mit Unterschriftsfeldern
 */
add_action('wp_ajax_dgptm_export_wahlprotokoll', 'dgptm_export_wahlprotokoll_fn');
function dgptm_export_wahlprotokoll_fn() {
    if (!dgptm_is_manager()) wp_die('Nicht autorisiert.');
    if (empty($_GET['poll_id'])) wp_die('Keine poll_id.');

    global $wpdb;
    $pid = intval($_GET['poll_id']);
    $wahlleiter = isset($_GET['wahlleiter']) ? sanitize_text_field(wp_unslash($_GET['wahlleiter'])) : '';
    $vorstand   = isset($_GET['vorstand'])   ? sanitize_text_field(wp_unslash($_GET['vorstand']))   : '';

    $poll = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_polls WHERE id=%d", $pid));
    if (!$poll) wp_die('Umfrage nicht gefunden.');

    // FPDF laden
    $fpdf_paths = array(
        __DIR__ . '/../vendor/fpdf/fpdf.php',
        DGPTM_SUITE_PATH . 'libraries/fpdf/fpdf.php',
    );
    $fpdf_loaded = false;
    foreach ($fpdf_paths as $fp) {
        if (file_exists($fp)) { require_once $fp; $fpdf_loaded = true; break; }
    }
    if (!$fpdf_loaded || !class_exists('FPDF')) wp_die('FPDF nicht gefunden.');

    if (!class_exists('DGPTM_Protokoll_PDF')) {
        class DGPTM_Protokoll_PDF extends FPDF {
            public $protokoll_title = '';
            public $logo_path = '';

            function Header() {
                // Logo oben rechts
                if ($this->logo_path && file_exists($this->logo_path)) {
                    $this->Image($this->logo_path, 160, 8, 35);
                }
                $this->SetFont('Arial', 'B', 16);
                $this->Cell(0, 10, $this->enc('WAHLPROTOKOLL'), 0, 1, 'C');
                if ($this->protokoll_title) {
                    $this->SetFont('Arial', '', 12);
                    $this->Cell(0, 7, $this->enc($this->protokoll_title), 0, 1, 'C');
                }
                $this->Line(10, $this->GetY() + 2, 200, $this->GetY() + 2);
                $this->Ln(5);
            }

            function Footer() {
                $this->SetY(-15);
                $this->SetFont('Arial', 'I', 8);
                $this->Cell(0, 10, 'Seite ' . $this->PageNo() . '/{nb}  |  Erstellt: ' . date('d.m.Y H:i'), 0, 0, 'C');
            }

            function enc($s) { return iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $s); }

            function SectionTitle($title) {
                $this->SetFont('Arial', 'B', 12);
                $this->SetFillColor(240, 240, 240);
                $this->Cell(0, 7, $this->enc($title), 0, 1, 'L', true);
                $this->Ln(2);
            }

            function InfoRow($label, $value) {
                $this->SetFont('Arial', 'B', 10);
                $this->Cell(55, 6, $this->enc($label . ':'), 0, 0);
                $this->SetFont('Arial', '', 10);
                $this->Cell(0, 6, $this->enc($value), 0, 1);
            }

            function ResultBar($label, $votes, $pct, $is_winner) {
                $this->SetFont('Arial', $is_winner ? 'B' : '', 10);
                $bar_w = 80;
                $fill_w = max(1, round($bar_w * $pct / 100));

                // Label
                $prefix = $is_winner ? chr(214) . ' ' : '  '; // Ö as checkmark substitute
                $this->Cell(60, 6, $this->enc($label), 0, 0);

                // Bar
                $x = $this->GetX();
                $y = $this->GetY();
                $this->SetFillColor($is_winner ? 34 : 220, $is_winner ? 163 : 38, $is_winner ? 74 : 38);
                $this->Rect($x, $y, $fill_w, 5, 'F');
                $this->SetDrawColor(200, 200, 200);
                $this->Rect($x, $y, $bar_w, 5);
                $this->SetX($x + $bar_w + 3);

                // Value
                $this->SetFont('Arial', 'B', 10);
                $this->Cell(0, 6, $votes . ' (' . $pct . '%)', 0, 1);
                $this->SetDrawColor(0, 0, 0);
            }

            function SignatureField($label, $name) {
                $this->Ln(3);
                $this->SetFont('Arial', '', 10);
                $this->Cell(0, 6, $this->enc($label . ($name ? ': ' . $name : '')), 0, 1);
                $this->Ln(12);
                $x = $this->GetX();
                $y = $this->GetY();
                $this->Line($x, $y, $x + 80, $y);
                $this->SetFont('Arial', 'I', 8);
                $this->Cell(80, 5, $this->enc('Unterschrift ' . $label), 0, 0);
                $this->Cell(20, 5, '', 0, 0);
                // Date field
                $x2 = $this->GetX();
                $this->Line($x2, $y, $x2 + 50, $y);
                $this->Cell(50, 5, $this->enc('Datum'), 0, 1);
                $this->Ln(5);
            }
        }
    }

    $pdf = new DGPTM_Protokoll_PDF();
    $pdf->protokoll_title = $poll->name;

    // Logo for PDF header (download to temp if URL, skip SVG as FPDF can't handle it)
    $logo_url = get_option('dgptm_beamer_logo', '');
    if (!empty($logo_url) && !preg_match('/\.svg$/i', $logo_url)) {
        $tmp = download_url($logo_url, 10);
        if (!is_wp_error($tmp)) {
            $pdf->logo_path = $tmp;
        }
    }
    // Fallback: use poll logo if set and not SVG
    if (empty($pdf->logo_path) && !empty($poll->logo_url) && !preg_match('/\.svg$/i', $poll->logo_url)) {
        $tmp = download_url($poll->logo_url, 10);
        if (!is_wp_error($tmp)) {
            $pdf->logo_path = $tmp;
        }
    }

    $pdf->AliasNbPages();
    $pdf->AddPage();

    // === Allgemeine Infos ===
    $pdf->SectionTitle('Allgemeine Angaben');
    $pdf->InfoRow('Umfrage', $poll->name . ' (ID: ' . $poll->id . ')');
    $pdf->InfoRow('Status', $poll->status);
    $pdf->InfoRow('Erstellt', date('d.m.Y H:i', strtotime($poll->created)));
    if (!empty($poll->ended)) {
        $pdf->InfoRow('Beendet', date('d.m.Y H:i', strtotime($poll->ended)));
    }

    $attendees = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}dgptm_abstimmung_participants WHERE poll_id=%d", $pid
    ));
    $pdf->InfoRow('Anwesende Teilnehmer', $attendees);
    if ($wahlleiter) $pdf->InfoRow('Wahlleiter', $wahlleiter);
    if ($vorstand) $pdf->InfoRow('Vorstand', $vorstand);
    $pdf->Ln(3);

    // === Fragen / Abstimmungen ===
    $questions = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_poll_questions WHERE poll_id=%d ORDER BY created ASC", $pid
    ));

    if ($questions) {
        $q_num = 0;
        foreach ($questions as $q) {
            $q_num++;
            $pdf->SectionTitle('Abstimmung ' . $q_num . ': ' . $q->question);

            // Meta
            $vote_type_label = ($q->vote_type ?? 'subject') === 'person' ? 'Personenwahl' : 'Sachthema';
            $pdf->InfoRow('Typ', $vote_type_label);

            $seats = (int) ($q->seats ?? 0);
            if (($q->vote_type ?? '') === 'person' && $seats > 0) {
                $pdf->InfoRow('Zu waehlende Sitze', $seats);
            }

            $maj_labels = array('simple' => 'Einfache Mehrheit', 'two_thirds' => '2/3-Mehrheit', 'absolute' => 'Absolute Mehrheit');
            $pdf->InfoRow('Mehrheitsregel', $maj_labels[$q->majority_type ?? 'simple'] ?? 'Einfache Mehrheit');

            if ((int)($q->quorum ?? 0) > 0) {
                $pdf->InfoRow('Quorum', (int)$q->quorum . ' Stimmen');
            }

            $pdf->InfoRow('Anonym', $q->is_anonymous ? 'Ja' : 'Nein');

            // Zeiten
            if (!empty($q->started_at)) {
                $pdf->InfoRow('Beginn', date('d.m.Y H:i:s', strtotime($q->started_at)));
            }
            if (!empty($q->ended)) {
                $pdf->InfoRow('Ende', date('d.m.Y H:i:s', strtotime($q->ended)));
            }
            if (!empty($q->started_at) && !empty($q->ended)) {
                $dur = strtotime($q->ended) - strtotime($q->started_at);
                $pdf->InfoRow('Dauer', $dur . ' Sekunden');
            }

            // Ergebnis
            $choices = json_decode($q->choices, true);
            if (!is_array($choices)) $choices = array();

            $total = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}dgptm_abstimmung_votes WHERE question_id=%d AND is_invalid=0", $q->id
            ));
            $invalid = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}dgptm_abstimmung_votes WHERE question_id=%d AND is_invalid=1", $q->id
            ));

            $pdf->InfoRow('Abgegebene Stimmen', $total . ' gueltig' . ($invalid > 0 ? ', ' . $invalid . ' ungueltig' : ''));
            $pdf->InfoRow('Wahlbeteiligung', $attendees > 0 ? round($total / $attendees * 100) . '% (' . $total . '/' . $attendees . ')' : '-');

            $pdf->Ln(2);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(0, 6, $pdf->enc('Ergebnis:'), 0, 1);
            $pdf->Ln(1);

            // Vote counts per choice
            $vote_counts = array_fill(0, count($choices), 0);
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT choice_index, COUNT(*) cnt FROM {$wpdb->prefix}dgptm_abstimmung_votes WHERE question_id=%d AND is_invalid=0 GROUP BY choice_index", $q->id
            ));
            foreach ($rows as $r) {
                if (isset($vote_counts[$r->choice_index])) {
                    $vote_counts[$r->choice_index] = (int)$r->cnt;
                }
            }

            // Majority evaluation
            $vote_map = array();
            foreach ($vote_counts as $idx => $cnt) { $vote_map[$idx] = $cnt; }
            $majority = function_exists('dgptm_evaluate_majority')
                ? dgptm_evaluate_majority($vote_map, $total, $attendees, $q->majority_type ?? 'simple', (int)($q->quorum ?? 0), $q->vote_type ?? 'subject', (int)($q->seats ?? 0))
                : array('passed' => false, 'winners' => array(), 'label' => '', 'runoff' => false);

            $winners = $majority['winners'] ?? array();

            foreach ($choices as $ci => $ctxt) {
                $cnt = $vote_counts[$ci] ?? 0;
                $pct = $total > 0 ? round($cnt / $total * 100) : 0;
                $is_winner = in_array($ci, $winners);
                $pdf->ResultBar($ctxt, $cnt, $pct, $is_winner);
            }

            // Summary line
            $pdf->Ln(2);
            $pdf->SetFont('Arial', 'B', 11);
            $icon = '';
            if (!empty($majority['runoff'])) {
                $icon = '! ';
            } elseif ($majority['passed']) {
                $icon = '+ ';
            } else {
                $icon = '- ';
            }
            $pdf->Cell(0, 7, $pdf->enc($icon . ($majority['label'] ?? '')), 0, 1);
            $pdf->SetFont('Arial', '', 10);
            $pdf->Ln(3);
        }
    }

    // === Unterschriften ===
    $pdf->Ln(5);
    $pdf->SectionTitle('Unterschriften');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, $pdf->enc('Hiermit wird die Richtigkeit des Wahlprotokolls bestaetigt.'), 0, 1);

    $pdf->SignatureField('Wahlleiter', $wahlleiter);
    $pdf->SignatureField('Vorstand', $vorstand);

    $pdf->Output('I', 'Wahlprotokoll_' . $pid . '_' . date('Y-m-d') . '.pdf');

    // Cleanup temp logo file
    if (!empty($pdf->logo_path) && file_exists($pdf->logo_path)) {
        @unlink($pdf->logo_path);
    }
    exit;
}
