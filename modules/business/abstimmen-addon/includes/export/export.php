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
