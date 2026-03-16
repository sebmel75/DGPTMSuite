<?php
if (!defined('ABSPATH')) exit;

/**
 * Beamer-State aus der Datenbank lesen.
 * Vertraegt sowohl JSON-Strings als auch bereits deserialisierte Arrays
 * (PHP 8 wirft einen TypeError wenn json_decode() ein Array bekommt).
 */
if (!function_exists('dgptm_get_beamer_state')) {
    function dgptm_get_beamer_state() {
        $raw = get_option('dgptm_beamer_state', array('mode' => 'auto'));
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : array('mode' => 'auto');
        }
        return array('mode' => 'auto');
    }
}

/**
 * Evaluate majority result for a question.
 *
 * @param array  $vote_counts  Associative array: choice_index => count
 * @param int    $total_votes  Total valid votes cast
 * @param int    $attendees    Number of attendees (participants)
 * @param string $majority_type 'simple', 'two_thirds', 'absolute'
 * @param int    $quorum       Minimum votes required (0 = none)
 * @return array ['passed' => bool, 'winner_index' => int|null, 'label' => string, 'quorum_met' => bool]
 */
if (!function_exists('dgptm_evaluate_majority')) {
    function dgptm_evaluate_majority($vote_counts, $total_votes, $attendees, $majority_type = 'simple', $quorum = 0) {
        $result = [
            'passed'       => false,
            'winner_index' => null,
            'label'        => '',
            'quorum_met'   => ($quorum <= 0 || $total_votes >= $quorum),
            'quorum'       => $quorum,
            'total_votes'  => $total_votes,
            'attendees'    => $attendees,
        ];

        if (!$result['quorum_met']) {
            $result['label'] = 'Quorum nicht erreicht (' . $total_votes . '/' . $quorum . ')';
            return $result;
        }

        if (empty($vote_counts) || $total_votes === 0) {
            $result['label'] = 'Keine Stimmen abgegeben';
            return $result;
        }

        // Find the winner (highest votes)
        arsort($vote_counts);
        $winner_index = array_key_first($vote_counts);
        $winner_votes = $vote_counts[$winner_index];
        $result['winner_index'] = $winner_index;

        $threshold = 0;
        $rule_label = '';

        switch ($majority_type) {
            case 'two_thirds':
                $threshold = $total_votes * (2 / 3);
                $rule_label = '2/3-Mehrheit';
                break;
            case 'absolute':
                $threshold = $attendees / 2;
                $rule_label = 'Absolute Mehrheit';
                break;
            case 'simple':
            default:
                $threshold = $total_votes / 2;
                $rule_label = 'Einfache Mehrheit';
                break;
        }

        $result['passed'] = ($winner_votes > $threshold);
        $status = $result['passed'] ? 'Angenommen' : 'Abgelehnt';
        $result['label'] = $status . ' (' . $rule_label . ')';

        return $result;
    }
}

/**
 * Calculate remaining seconds for a question's timer.
 *
 * @param object $question DB row with started_at and time_limit
 * @return int|null Remaining seconds, null if no timer, negative if expired
 */
if (!function_exists('dgptm_get_remaining_seconds')) {
    function dgptm_get_remaining_seconds($question) {
        if (empty($question->time_limit) || (int)$question->time_limit <= 0) {
            return null;
        }
        if (empty($question->started_at)) {
            return (int)$question->time_limit;
        }
        $started = strtotime($question->started_at);
        $expires = $started + (int)$question->time_limit;
        return $expires - time();
    }
}

/**
 * Check if a question's timer has expired and auto-close if configured.
 *
 * @param object $question DB row
 * @return bool True if question was auto-closed
 */
if (!function_exists('dgptm_check_auto_close')) {
    function dgptm_check_auto_close($question) {
        if (empty($question->auto_close) || $question->status !== 'active') {
            return false;
        }
        $remaining = dgptm_get_remaining_seconds($question);
        if ($remaining !== null && $remaining <= 0) {
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'dgptm_abstimmung_poll_questions',
                ['status' => 'stopped', 'ended' => current_time('mysql')],
                ['id' => $question->id]
            );
            return true;
        }
        return false;
    }
}

/**
 * Check if a participant already voted on a specific question (anonymous tracking).
 *
 * @param int $participant_id Participant DB row ID
 * @param int $question_id   Question ID
 * @return bool
 */
if (!function_exists('dgptm_has_voted_anonymous')) {
    function dgptm_has_voted_anonymous($participant_id, $question_id) {
        global $wpdb;
        $raw = $wpdb->get_var($wpdb->prepare(
            "SELECT voted_questions FROM {$wpdb->prefix}dgptm_abstimmung_participants WHERE id = %d",
            $participant_id
        ));
        if (empty($raw)) return false;
        $arr = json_decode($raw, true);
        return is_array($arr) && in_array((int)$question_id, $arr, true);
    }
}

/**
 * Mark that a participant voted on a question (anonymous tracking).
 *
 * @param int $participant_id Participant DB row ID
 * @param int $question_id   Question ID
 */
if (!function_exists('dgptm_mark_voted_anonymous')) {
    function dgptm_mark_voted_anonymous($participant_id, $question_id) {
        global $wpdb;
        $raw = $wpdb->get_var($wpdb->prepare(
            "SELECT voted_questions FROM {$wpdb->prefix}dgptm_abstimmung_participants WHERE id = %d",
            $participant_id
        ));
        $arr = !empty($raw) ? json_decode($raw, true) : [];
        if (!is_array($arr)) $arr = [];
        if (!in_array((int)$question_id, $arr, true)) {
            $arr[] = (int)$question_id;
            $wpdb->update(
                $wpdb->prefix . 'dgptm_abstimmung_participants',
                ['voted_questions' => wp_json_encode($arr)],
                ['id' => $participant_id]
            );
        }
    }
}

/**
 * Find participant record for current user/cookie.
 *
 * @param int $poll_id
 * @return object|null Participant DB row
 */
if (!function_exists('dgptm_get_current_participant')) {
    function dgptm_get_current_participant($poll_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'dgptm_abstimmung_participants';

        if (is_user_logged_in()) {
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE poll_id = %d AND user_id = %d LIMIT 1",
                $poll_id, get_current_user_id()
            ));
        }

        $cookie = isset($_COOKIE[DGPTMVOTE_COOKIE]) ? sanitize_text_field($_COOKIE[DGPTMVOTE_COOKIE]) : '';
        if (!empty($cookie)) {
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE poll_id = %d AND cookie_id = %s LIMIT 1",
                $poll_id, $cookie
            ));
        }

        return null;
    }
}

// Manager-Recht über Usermeta
if (!function_exists('dgptm_is_manager')) {
    function dgptm_is_manager($user_id = null) {
        if ($user_id === null) $user_id = get_current_user_id();
        if (!$user_id) return current_user_can('manage_options');
        if (current_user_can('manage_options')) return true;
        $flag = get_user_meta($user_id, 'toggle_abstimmungsmanager', true);
        return (bool) $flag;
    }
}

// Usermeta UI + speichern
if (!function_exists('dgptm_user_field_abstimmungsmanager')) {
    function dgptm_user_field_abstimmungsmanager($user){
        if(!current_user_can('manage_options')) return;
        $val = get_user_meta($user->ID,'toggle_abstimmungsmanager',true);
        ?>
        <h2>Abstimmungsmanager</h2>
        <table class="form-table" role="presentation">
          <tr>
            <th><label for="toggle_abstimmungsmanager">Ist Abstimmungsmanager?</label></th>
            <td>
              <label style="display:inline-flex;align-items:center;gap:10px;">
                <span style="display:inline-block;width:46px;height:24px;position:relative;">
                  <input type="checkbox" name="toggle_abstimmungsmanager" id="toggle_abstimmungsmanager" value="1" <?php checked($val, '1'); ?> style="display:none;">
                  <span style="position:absolute;inset:0;border-radius:24px;background:#ccc;"></span>
                  <span style="position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:#fff;transition:transform .2s;" id="dgptm_knob"></span>
                </span>
                <span>Aktiviert Zugriff auf [manage_poll] und [beamer_view]</span>
              </label>
              <script>
              (function(){
                var cb=document.getElementById('toggle_abstimmungsmanager');
                if(!cb) return;
                var knob=document.getElementById('dgptm_knob');
                var wrap=cb.previousElementSibling;
                function paint(){
                  wrap.firstElementChild.style.background = cb.checked ? '#4CAF50' : '#ccc';
                  knob.style.transform = cb.checked ? 'translateX(22px)' : 'translateX(0)';
                }
                wrap.addEventListener('click',function(e){ cb.checked=!cb.checked; paint(); });
                paint();
              })();
              </script>
              <p class="description">Schaltet Manager-Rechte unabhängig von der Rolle frei.</p>
            </td>
          </tr>
        </table>
        <?php
    }
}

if (!function_exists('dgptm_save_user_field_abstimmungsmanager')) {
    function dgptm_save_user_field_abstimmungsmanager($user_id){
        if(!current_user_can('manage_options')) return;
        $val = isset($_POST['toggle_abstimmungsmanager']) ? '1' : '0';
        update_user_meta($user_id, 'toggle_abstimmungsmanager', $val);
    }
}

// Shortcode 1/0 je nach Toggle
if (!function_exists('dgptm_shortcode_manager_toggle')) {
    function dgptm_shortcode_manager_toggle(){
        $user_id = get_current_user_id();
        if(!$user_id) return '0';
        $v = get_user_meta($user_id,'toggle_abstimmungsmanager',true);
        return $v==='1' ? '1' : '0';
    }
}

// helpers
if (!function_exists('dgptm_is_image_ext')) {
    function dgptm_is_image_ext($url){
        $ext = strtolower(pathinfo(parse_url($url,PHP_URL_PATH)??'',PATHINFO_EXTENSION));
        return in_array($ext,array('jpg','jpeg','png'),true);
    }
}

// Öffentliche Ziel-URL (?dgptm_member=1)
if (!function_exists('dgptm_add_query_var')) {
    function dgptm_add_query_var($vars){ $vars[]='dgptm_member'; $vars[]='poll_id'; $vars[]='token'; return $vars; }
}

if (!function_exists('dgptm_template_redirect')) {
    function dgptm_template_redirect(){
        if(get_query_var('dgptm_member')){
            status_header(200);
            nocache_headers();
            echo '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
            echo '<title>Abstimmung</title>';
            wp_head();
            echo '</head><body>';
            echo do_shortcode('[member_vote]');
            wp_footer();
            echo '</body></html>';
            exit;
        }
    }
}
