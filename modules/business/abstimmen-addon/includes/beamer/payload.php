<?php
// File: includes/beamer/payload.php

if (!defined('ABSPATH')) exit;

/**
 * Beamer-Payload: Baut Ergebnis-Daten fuer eine Frage inkl. Majority-Auswertung.
 */
if (!function_exists('dgptm_build_results_payload')) {
    function dgptm_build_results_payload($question_id, $count_only = false, $poll_id = 0) {
        global $wpdb;
        $q = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_poll_questions WHERE id = %d", $question_id
        ));
        if (!$q) return array('released' => false, 'total_votes' => 0, 'chart_type' => 'bar');

        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}dgptm_abstimmung_votes WHERE question_id = %d AND is_invalid = 0", $question_id
        ));

        if ($count_only || !$q->results_released) {
            return array('released' => false, 'total_votes' => $total, 'chart_type' => $q->chart_type ?: 'bar');
        }

        $choices = json_decode($q->choices, true);
        if (!is_array($choices)) $choices = array();

        $vote_counts = array_fill(0, count($choices), 0);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT choice_index, COUNT(*) cnt FROM {$wpdb->prefix}dgptm_abstimmung_votes WHERE question_id = %d AND is_invalid = 0 GROUP BY choice_index",
            $question_id
        ));
        if ($rows) {
            foreach ($rows as $r) {
                if (isset($vote_counts[$r->choice_index])) {
                    $vote_counts[$r->choice_index] = (int) $r->cnt;
                }
            }
        }

        // Choice images
        $choice_images = null;
        if (!empty($q->choice_images)) {
            $decoded = json_decode($q->choice_images, true);
            if (is_array($decoded)) $choice_images = $decoded;
        }

        $result = array(
            'released'      => true,
            'choices'       => array_values($choices),
            'votes'         => array_values($vote_counts),
            'total_votes'   => $total,
            'chart_type'    => $q->chart_type ?: 'bar',
            'display_type'  => $q->display_type ?? 'cards',
            'choice_images' => $choice_images,
        );

        // Majority-Auswertung
        if (function_exists('dgptm_evaluate_majority') && $poll_id > 0) {
            $attendees = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}dgptm_abstimmung_participants WHERE poll_id = %d", $poll_id
            ));
            $vote_map = array();
            foreach ($vote_counts as $idx => $cnt) {
                $vote_map[$idx] = $cnt;
            }
            $majority = dgptm_evaluate_majority(
                $vote_map, $total, $attendees,
                $q->majority_type ?? 'simple',
                (int) ($q->quorum ?? 0)
            );
            $result['majority'] = $majority;
        }

        return $result;
    }
}

/**
 * AJAX-Handler: Beamer-Payload mit Timer, Auto-Close, Majority.
 */
if (!function_exists('dgptm_get_beamer_payload_fn')) {
    add_action('wp_ajax_dgptm_get_beamer_payload', 'dgptm_get_beamer_payload_fn');
    add_action('wp_ajax_nopriv_dgptm_get_beamer_payload', 'dgptm_get_beamer_payload_fn');

    function dgptm_get_beamer_payload_fn() {
        global $wpdb;
        $state = dgptm_get_beamer_state();

        $poll = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_polls WHERE status='active' LIMIT 1");
        $payload = array(
            'beamer_state'    => $state,
            'active_poll'     => null,
            'active_question' => null,
            'active_results'  => null,
            'attendees'       => 0,
            'timer'           => array('remaining_seconds' => null, 'time_limit' => 0, 'auto_close' => 0, 'auto_closed' => false),
        );

        if ($poll) {
            $payload['active_poll'] = array(
                'id'                    => $poll->id,
                'name'                  => $poll->name,
                'logo_url'              => $poll->logo_url,
                'beamer_content'        => $poll->beamer_content ?? '',
                'beamer_content_active' => (int) ($poll->beamer_content_active ?? 0),
            );

            $attendees = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}dgptm_abstimmung_participants WHERE poll_id = %d", $poll->id
            ));
            $payload['attendees'] = $attendees;

            $aq = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_poll_questions WHERE poll_id = %d AND status = 'active' LIMIT 1", $poll->id
            ));

            // Auto-close check
            $auto_closed = false;
            if ($aq && function_exists('dgptm_check_auto_close')) {
                $auto_closed = dgptm_check_auto_close($aq);
                if ($auto_closed) {
                    $aq = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_poll_questions WHERE id = %d", $aq->id
                    ));
                }
            }

            if ($aq && $aq->status === 'active') {
                $payload['active_question'] = array(
                    'id'            => $aq->id,
                    'question'      => $aq->question,
                    'status'        => $aq->status,
                    'is_anonymous'  => (int) $aq->is_anonymous,
                    'majority_type' => $aq->majority_type ?? 'simple',
                    'quorum'        => (int) ($aq->quorum ?? 0),
                    'choices'       => $aq->choices,
                );
                $res = dgptm_build_results_payload($aq->id, true, (int) $poll->id);
                $payload['active_results'] = $res;

                // Timer
                $remaining = function_exists('dgptm_get_remaining_seconds') ? dgptm_get_remaining_seconds($aq) : null;
                $payload['timer'] = array(
                    'remaining_seconds' => $remaining,
                    'time_limit'        => (int) ($aq->time_limit ?? 0),
                    'auto_close'        => (int) ($aq->auto_close ?? 0),
                    'auto_closed'       => false,
                );
            } elseif ($aq && $aq->status === 'stopped') {
                // Frage wurde gerade auto-closed oder manuell gestoppt
                $stopped_q = array(
                    'id'               => $aq->id,
                    'question'         => $aq->question,
                    'status'           => 'stopped',
                    'results_released' => (int) $aq->results_released,
                    'is_anonymous'     => (int) $aq->is_anonymous,
                    'majority_type'    => $aq->majority_type ?? 'simple',
                    'quorum'           => (int) ($aq->quorum ?? 0),
                    'choices'          => $aq->choices,
                );
                // Include full results data so beamer can render charts/images
                if ((int) $aq->results_released === 1) {
                    $stopped_q = $stopped_q + dgptm_build_results_payload($aq->id, false, (int) $poll->id);
                }
                $payload['active_question'] = $stopped_q;
                $payload['timer'] = array(
                    'remaining_seconds' => 0,
                    'time_limit'        => (int) ($aq->time_limit ?? 0),
                    'auto_close'        => (int) ($aq->auto_close ?? 0),
                    'auto_closed'       => $auto_closed,
                );
            }
        }

        // results_one Modus
        if (isset($state['mode']) && $state['mode'] === 'results_one' && !empty($state['question_id'])) {
            $qid = intval($state['question_id']);
            $q = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_poll_questions WHERE id = %d", $qid
            ));
            if ($q && (int) $q->results_released === 1 && $q->status !== 'active') {
                $pid = (int) $q->poll_id;
                $payload['state_question'] = array(
                    'id'            => $q->id,
                    'question'      => $q->question,
                    'majority_type' => $q->majority_type ?? 'simple',
                    'quorum'        => (int) ($q->quorum ?? 0),
                    'choices'       => $q->choices,
                ) + dgptm_build_results_payload($q->id, false, $pid);
            }
        }

        // results_all Modus
        if (isset($state['mode']) && $state['mode'] === 'results_all' && !empty($state['poll_id'])) {
            $pid = intval($state['poll_id']);
            $qs = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_poll_questions WHERE poll_id = %d AND status = 'stopped' AND results_released = 1 AND in_overall = 1 ORDER BY created ASC",
                $pid
            ));
            $arr = array();
            foreach ($qs as $q) {
                $arr[] = array(
                    'id'            => $q->id,
                    'question'      => $q->question,
                    'majority_type' => $q->majority_type ?? 'simple',
                    'quorum'        => (int) ($q->quorum ?? 0),
                    'choices'       => $q->choices,
                ) + dgptm_build_results_payload($q->id, false, $pid);
            }
            $payload['state_questions'] = $arr;
        }

        wp_send_json_success($payload);
    }
}
