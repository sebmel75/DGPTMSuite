<?php
// File: includes/beamer/payload.php

if (!defined('ABSPATH')) exit;

/**
 * Beamer-Payload inkl. Anwesendenzahl (Teilnehmer im Poll)
 */
if (!function_exists('dgptm_build_results_payload')) {
    function dgptm_build_results_payload($question_id, $count_only=false){
        global $wpdb;
        $q=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_poll_questions WHERE id=%d",$question_id));
        if(!$q) return array('released'=>false,'total_votes'=>0,'chart_type'=>'bar');
        if($count_only || !$q->results_released){
            $total = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}dgptm_abstimmung_votes WHERE question_id=%d AND is_invalid=0",$question_id));
            return array('released'=>false,'total_votes'=>$total,'chart_type'=>$q->chart_type ? $q->chart_type : 'bar');
        }
        $choices = json_decode($q->choices,true); if(!is_array($choices)) $choices=array();
        $vote_counts = array_fill(0, count($choices), 0);
        $rows = $wpdb->get_results($wpdb->prepare("
            SELECT choice_index, COUNT(*) cnt FROM {$wpdb->prefix}dgptm_abstimmung_votes
            WHERE question_id=%d AND is_invalid=0 GROUP BY choice_index
        ", $question_id));
        if($rows){ foreach($rows as $r){ if(isset($vote_counts[$r->choice_index])) $vote_counts[$r->choice_index]=(int)$r->cnt; } }
        return array('released'=>true,'choices'=>array_values($choices),'votes'=>array_values($vote_counts),'chart_type'=>$q->chart_type ? $q->chart_type : 'bar');
    }
}

if (!function_exists('dgptm_get_beamer_payload_fn')) {
    add_action('wp_ajax_dgptm_get_beamer_payload','dgptm_get_beamer_payload_fn');
    add_action('wp_ajax_nopriv_dgptm_get_beamer_payload','dgptm_get_beamer_payload_fn');
    function dgptm_get_beamer_payload_fn(){
        global $wpdb;
        $state = json_decode( get_option('dgptm_beamer_state', wp_json_encode(array('mode'=>'auto'))), true );
        if(!is_array($state)) $state=array('mode'=>'auto');

        $poll=$wpdb->get_row("SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_polls WHERE status='active' LIMIT 1");
        $payload=array('beamer_state'=>$state,'active_poll'=>null,'active_question'=>null,'active_results'=>null,'attendees'=>0);

        if($poll){
            $payload['active_poll']=array('id'=>$poll->id,'name'=>$poll->name,'logo_url'=>$poll->logo_url);

            // Anwesende Mitglieder
            $attendees = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}dgptm_abstimmung_participants WHERE poll_id=%d",$poll->id));
            $payload['attendees'] = $attendees;

            $aq=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_poll_questions WHERE poll_id=%d AND status='active' LIMIT 1",$poll->id));
            if($aq){
                $payload['active_question']=array('id'=>$aq->id,'question'=>$aq->question);
                $res = dgptm_build_results_payload($aq->id, true);
                $payload['active_results']=$res;
            }
        }

        if(isset($state['mode']) && $state['mode']==='results_one' && !empty($state['question_id'])){
            $qid=intval($state['question_id']);
            $q=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_poll_questions WHERE id=%d",$qid));
            if($q && (int)$q->results_released===1 && $q->status!=='active'){
                $payload['state_question']=array('id'=>$q->id,'question'=>$q->question) + dgptm_build_results_payload($q->id, false);
            }
        }

        if(isset($state['mode']) && $state['mode']==='results_all' && !empty($state['poll_id'])){
            $pid=intval($state['poll_id']);
            $qs=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_poll_questions WHERE poll_id=%d AND status='stopped' AND results_released=1 AND in_overall=1 ORDER BY created ASC",$pid));
            $arr=array();
            foreach($qs as $q){
                $tmp=array('id'=>$q->id,'question'=>$q->question) + dgptm_build_results_payload($q->id, false);
                $arr[]=$tmp;
            }
            $payload['state_questions']=$arr;
        }

        wp_send_json_success($payload);
    }
}
