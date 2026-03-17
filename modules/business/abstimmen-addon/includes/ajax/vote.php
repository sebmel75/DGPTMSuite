<?php
if (!defined('ABSPATH')) exit;

// Load member view
if (!function_exists('dgptm_get_member_view_fn')) {
    add_action('wp_ajax_dgptm_get_member_view','dgptm_get_member_view_fn');
    add_action('wp_ajax_nopriv_dgptm_get_member_view','dgptm_get_member_view_fn');
    function dgptm_get_member_view_fn(){
        global $wpdb;

        $req_pid = isset($_POST['poll_id'])? intval($_POST['poll_id']) : 0;
        $token   = isset($_POST['token']) ? sanitize_text_field(wp_unslash($_POST['token'])) : '';

        $poll=null;
        if($req_pid){
            $poll=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_polls WHERE id=%d",$req_pid));
            if(!$poll || $poll->status!=='active') $poll=null;
        }
        if(!$poll){
            $poll=$wpdb->get_row("SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_polls WHERE status='active' LIMIT 1");
        }
        if(!$poll) {
            wp_send_json_error(array('html'=>'<div style="text-align:center;"><em>Keine aktive Umfrage.</em></div>'));
        }

        // Guest gate: wenn guest_voting deaktiviert und nicht eingeloggt
        if(isset($poll->guest_voting) && empty($poll->guest_voting) && !is_user_logged_in()){
            wp_send_json_success(array(
                'html' => '<div class="dgptm-guest-blocked" style="text-align:center;padding:30px;"><p>Bitte melden Sie sich an um abzustimmen.</p><a href="' . esc_url(wp_login_url()) . '" class="btn" style="display:inline-block;margin-top:12px;padding:10px 20px;background:#2d6cdf;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;">Anmelden</a></div>',
                'has_active' => false,
            ));
        }

        // Token handling
        $valid_token = false;
        if($token!==''){
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_participants WHERE poll_id=%d AND token=%s LIMIT 1", $poll->id, $token));
            if($row){
                $valid_token = true;
                if(empty($_COOKIE[DGPTMVOTE_COOKIE])){
                    $rand='tok_'.$token;
                    setcookie(DGPTMVOTE_COOKIE, $rand, time()+3600*24*365, '/');
                    $_COOKIE[DGPTMVOTE_COOKIE]=$rand;
                }
            }
        }

        $q=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_poll_questions WHERE poll_id=%d AND status='active' LIMIT 1",$poll->id));
        if(!$q) {
            wp_send_json_error(array('html'=>'<div style="text-align:center;"><em>Keine aktive Frage.</em></div>'));
        }

        if(!is_user_logged_in() && empty($_COOKIE['dgptm_participant_name']) && !$valid_token){
            $login = do_shortcode('[elementor-template id="32160"]');
            $form = '<div class="box" style="max-width:620px;margin:10px auto;">
                <h3>Teilnahme</h3>
                <div style="margin-bottom:8px;">'.$login.'</div>
                <p><strong>ODER</strong> tragen Sie Ihren Namen ein:</p>
                <p style="color:#a00;font-weight:700;">Bitte Namen korrekt und vollständig eingeben, ansonsten wird Ihre Stimme nicht gezählt!</p>
                <form id="dgptm_nameGateForm">
                    <input type="text" id="dgptm_gateName" placeholder="Vorname Nachname" style="width:100%;padding:10px;border-radius:8px;border:1px solid #bbb;">
                    <div style="margin-top:8px;"><button type="submit" class="btn">Speichern & weiter</button></div>
                </form>
            </div>';
            wp_send_json_error(array('html'=>$form));
        }

        $choices = json_decode($q->choices,true); if(!is_array($choices)) $choices=array();
        $choice_images = null;
        if (!empty($q->choice_images)) {
            $choice_images = json_decode($q->choice_images, true);
            if (!is_array($choice_images)) $choice_images = null;
        }
        $type = ($q->max_votes==1)?'radio':'checkbox';

        // Timer
        $timer_html = '';
        if (function_exists('dgptm_get_remaining_seconds')) {
            $remaining = dgptm_get_remaining_seconds($q);
            if ($remaining !== null) {
                $mins = floor(max(0, $remaining) / 60);
                $secs = max(0, $remaining) % 60;
                $ac = isset($q->auto_close) ? (int)$q->auto_close : 0;
                $timer_html = '<div class="dgptm-vote-timer" data-remaining="' . (int)$remaining . '" data-auto-close="' . $ac . '">';
                $timer_html .= '<span class="dgptm-countdown">' . $mins . ':' . str_pad($secs, 2, '0', STR_PAD_LEFT) . '</span>';
                $timer_html .= '</div>';
            }
        }

        // Anonym-Hinweis
        $anon_html = '';
        $submit_label = 'Abstimmen';
        if (!empty($q->is_anonymous)) {
            $anon_html = '<div class="dgptm-anon-notice" style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:10px 14px;margin:10px 0;font-size:13px;color:#1e40af;">Diese Abstimmung ist anonym. Ihre Stimme kann nicht zu Ihnen zurueckverfolgt werden.</div>';
            $submit_label = 'Abstimmen (endgueltig)';
        }

        // Mehrheitsregel-Info
        $majority_html = '';
        $maj = isset($q->majority_type) ? $q->majority_type : 'simple';
        $q_quorum = isset($q->quorum) ? (int)$q->quorum : 0;
        if ($maj !== 'simple' || $q_quorum > 0) {
            $labels = array('simple' => 'Einfache Mehrheit', 'two_thirds' => '2/3-Mehrheit', 'absolute' => 'Absolute Mehrheit');
            $majority_html = '<div class="dgptm-majority-info" style="text-align:center;font-size:12px;color:#6b7280;margin:8px 0;">Erforderlich: ' . ($labels[$maj] ?? $maj);
            if ($q_quorum > 0) $majority_html .= ' &middot; Quorum: ' . $q_quorum . ' Stimmen';
            $majority_html .= '</div>';
        }

        $html  = '<div class="row">';
        $html .= '<div class="col">';
        $html .= $timer_html;
        $html .= '<h3 style="text-align:center;margin:8px 0;">Aktive Frage</h3><strong style="display:block;text-align:center;margin-bottom:10px;">'.esc_html($q->question).'</strong>';
        $html .= $anon_html . $majority_html;
        $html .= '<form id="dgptm_memberVoteForm" data-qid="'.(int)$q->id.'" data-max-votes="'.(int)$q->max_votes.'">';
        $html .= '<input type="hidden" name="action" value="dgptm_cast_vote">';
        $html .= '<input type="hidden" name="question_id" value="'.(int)$q->id.'">';
        foreach($choices as $ix=>$txt){
            $img_html = '';
            if ($choice_images && !empty($choice_images[$ix])) {
                $img_html = '<img src="'.esc_url($choice_images[$ix]).'" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:6px;vertical-align:middle;margin-right:8px;">';
            }
            $html .= '<label class="choice" style="display:flex;align-items:center;gap:8px;"><input type="'.$type.'" name="choices[]" value="'.(int)$ix.'"> '.$img_html.esc_html($txt).'</label>';
        }
        $html .= '<div style="margin-top:10px;"><button type="submit" class="btn">' . esc_html($submit_label) . '</button></div></form><div id="dgptm_memberVoteFeedback" style="margin-top:8px;"></div></div>';
        $html .= '</div>';

        wp_send_json_success(array('html'=>$html,'question_id'=>$q->id));
    }
}

// join poll (attendance)
if (!function_exists('dgptm_join_poll_fn')) {
    add_action('wp_ajax_dgptm_join_poll','dgptm_join_poll_fn');
    add_action('wp_ajax_nopriv_dgptm_join_poll','dgptm_join_poll_fn');
    function dgptm_join_poll_fn(){
        global $wpdb;
        $pid = isset($_POST['poll_id']) ? intval($_POST['poll_id']) : 0;

        if(!$pid){
            $poll=$wpdb->get_row("SELECT id FROM {$wpdb->prefix}dgptm_abstimmung_polls WHERE status='active' LIMIT 1");
            if($poll) $pid=(int)$poll->id;
        }
        if(!$pid) wp_send_json_error('Keine aktive Umfrage.');

        // Cookie
        if(empty($_COOKIE[DGPTMVOTE_COOKIE]) && !empty($_COOKIE['dgptm_voteid'])){
            setcookie(DGPTMVOTE_COOKIE, sanitize_text_field($_COOKIE['dgptm_voteid']), time()+3600*24*365, '/');
            $_COOKIE[DGPTMVOTE_COOKIE] = sanitize_text_field($_COOKIE['dgptm_voteid']);
        }
        $cn=DGPTMVOTE_COOKIE;
        if(empty($_COOKIE[$cn])){
            $rand='anon_'.wp_generate_uuid4();
            setcookie($cn,$rand,time()+3600*24*365,'/');
            $_COOKIE[$cn]=$rand;
        }
        $cookie_id=sanitize_text_field($_COOKIE[$cn]);

        $tbl=$wpdb->prefix.'dgptm_abstimmung_participants';

        if(is_user_logged_in()){
            $uid=get_current_user_id();
            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tbl WHERE poll_id=%d AND user_id=%d",$pid,$uid));
            if(!$exists){
                $ud=get_userdata($uid);
                $fullname=trim(($ud->first_name?$ud->first_name:'').' '.($ud->last_name?$ud->last_name:''));
                if($fullname==='') $fullname=$ud->display_name;
                $wpdb->insert($tbl,array(
                    'poll_id'=>$pid,'user_id'=>$uid,'fullname'=>$fullname,'cookie_id'=>'','joined_time'=>current_time('mysql'),
                    'first_name'=>$ud->first_name ?? '','last_name'=>$ud->last_name ?? '','member_no'=>'','member_status'=>'','email'=>$ud->user_email,'token'=>'','source'=>'join'
                ));
            }
        } else {
            $has_token = false;
            if(!empty($_COOKIE[$cn]) && strpos($_COOKIE[$cn],'tok_')===0){ $has_token = true; }
            $pname = isset($_COOKIE['dgptm_participant_name'])? sanitize_text_field($_COOKIE['dgptm_participant_name']) : '';
            if(!$has_token && $pname===''){
                wp_send_json_error('Bitte zuerst Namen eingeben (oder einloggen).');
            }
            $exists=$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tbl WHERE poll_id=%d AND cookie_id=%s",$pid,$cookie_id));
            if(!$exists){
                $wpdb->insert($tbl,array(
                    'poll_id'=>$pid,'user_id'=>0,'fullname'=>$pname ? $pname : 'Gast','cookie_id'=>$cookie_id,'joined_time'=>current_time('mysql'),
                    'first_name'=>'','last_name'=>'','member_no'=>'','member_status'=>'','email'=>'','token'=>'','source'=>'join'
                ));
            }
        }
        wp_send_json_success('Teilnahme registriert.');
    }
}

// vote cast
if (!function_exists('dgptm_cast_vote_fn')) {
    add_action('wp_ajax_dgptm_cast_vote','dgptm_cast_vote_fn');
    add_action('wp_ajax_nopriv_dgptm_cast_vote','dgptm_cast_vote_fn');
    function dgptm_cast_vote_fn(){
        if(empty($_POST['question_id']) || empty($_POST['choices'])) wp_send_json_error('Keine gültigen Eingaben.');
        global $wpdb;
        $qid=intval($_POST['question_id']);
        $choices=(array)$_POST['choices'];
        $q=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_poll_questions WHERE id=%d",$qid));
        if(!$q || $q->status!=='active') wp_send_json_error('Diese Frage ist nicht mehr aktiv.');
        $maxv=(int)$q->max_votes;
        if(count($choices)>$maxv) wp_send_json_error('Maximal '.$maxv.' Antworten erlaubt.');

        // Check auto-close before accepting vote
        if (function_exists('dgptm_check_auto_close') && dgptm_check_auto_close($q)) {
            wp_send_json_error('Die Abstimmungszeit ist abgelaufen.');
        }

        // Get question's anonymous flag
        $is_anon = !empty($q->is_anonymous);

        if ($is_anon) {
            // === ANONYMOUS VOTING ===
            // Find participant
            if (!function_exists('dgptm_get_current_participant')) {
                wp_send_json_error('Anonyme Abstimmung ist derzeit nicht verfügbar.');
            }
            $participant = dgptm_get_current_participant((int)$q->poll_id);
            if (!$participant) {
                wp_send_json_error('Bitte treten Sie zuerst der Abstimmung bei.');
            }

            // Check if already voted (no re-voting for anonymous)
            if (function_exists('dgptm_has_voted_anonymous') && dgptm_has_voted_anonymous($participant->id, $qid)) {
                wp_send_json_error('Sie haben bei dieser Frage bereits abgestimmt. Anonyme Stimmen koennen nicht geaendert werden.');
            }

            // Insert votes WITHOUT identifying data
            $vt = current_time('mysql');
            foreach ($choices as $ch) {
                $wpdb->insert($wpdb->prefix . 'dgptm_abstimmung_votes', array(
                    'question_id'  => $qid,
                    'choice_index' => (int)$ch,
                    'user_id'      => 0,
                    'vote_time'    => $vt,
                    'ip'           => 'anonymous',
                    'is_invalid'   => 0,
                ));
            }

            // Track that this participant voted (but not how)
            if (function_exists('dgptm_mark_voted_anonymous')) {
                dgptm_mark_voted_anonymous($participant->id, $qid);
            }

            do_action('dgptm_vote_cast', $qid, $choices, 0, (int)$q->poll_id);
            wp_send_json_success('Ihre Stimme wurde anonym gezaehlt.');

        } else {
            // === NON-ANONYMOUS VOTING (existing logic) ===
            $user_id=0; $cookie_id='';
            if(is_user_logged_in()){
                $user_id=get_current_user_id();
            } else {
                $cn=DGPTMVOTE_COOKIE;
                if(empty($_COOKIE[$cn])){
                    $rand='anon_'.wp_generate_uuid4();
                    setcookie($cn,$rand,time()+3600*24*365,'/');
                    $_COOKIE[$cn]=$rand;
                }
                $cookie_id=sanitize_text_field($_COOKIE[$cn]);
            }

            if($user_id>0){
                $wpdb->delete($wpdb->prefix.'dgptm_abstimmung_votes',array('question_id'=>$qid,'user_id'=>$user_id));
            } else {
                $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}dgptm_abstimmung_votes WHERE question_id=%d AND ip=%s",$qid,'COOKIE:'.$cookie_id));
            }

            $vt=current_time('mysql');
            foreach($choices as $ch){
                $data=array(
                  'question_id'=>$qid,
                  'choice_index'=>(int)$ch,
                  'vote_time'=>$vt,
                  'is_invalid'=>0
                );
                if($user_id>0){
                    $data['user_id']=$user_id;
                    $data['ip']=$_SERVER['REMOTE_ADDR']??'';
                } else {
                    $data['user_id']=0;
                    $data['ip']='COOKIE:'.$cookie_id;
                }
                $wpdb->insert($wpdb->prefix.'dgptm_abstimmung_votes',$data);
            }

            $poll_id=(int)$q->poll_id;
            $tbl=$wpdb->prefix.'dgptm_abstimmung_participants';
            if($user_id>0){
                $exists=$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tbl WHERE poll_id=%d AND user_id=%d",$poll_id,$user_id));
                if(!$exists){
                    $ud=get_userdata($user_id);
                    $fullname=trim(($ud->first_name?$ud->first_name:'').' '.($ud->last_name?$ud->last_name:''));
                    if($fullname==='') $fullname=$ud->display_name;
                    $wpdb->insert($tbl,array('poll_id'=>$poll_id,'user_id'=>$user_id,'fullname'=>$fullname,'cookie_id'=>'','joined_time'=>current_time('mysql'),'source'=>'vote'));
                }
            } else {
                $pname = isset($_COOKIE['dgptm_participant_name'])? sanitize_text_field($_COOKIE['dgptm_participant_name']) : 'Anonym';
                $exists=$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tbl WHERE poll_id=%d AND cookie_id=%s",$poll_id,$cookie_id));
                if(!$exists){
                    $wpdb->insert($tbl,array('poll_id'=>$poll_id,'user_id'=>0,'fullname'=>$pname,'cookie_id'=>$cookie_id,'joined_time'=>current_time('mysql'),'source'=>'vote'));
                }
            }

            /**
             * Fires after a vote has been successfully cast.
             *
             * @param int   $qid      The question ID.
             * @param array $choices  The selected choice indices.
             * @param int   $user_id  The user ID (0 if anonymous).
             * @param int   $poll_id  The poll ID.
             */
            do_action('dgptm_vote_cast', $qid, $choices, $user_id, (int)$q->poll_id);

            wp_send_json_success('Stimme wurde erfolgreich abgegeben.');
        }
    }
}

// helper AJAX (active poll info & poll active)
add_action('wp_ajax_dgptm_get_active_poll_info','dgptm_get_active_poll_info_fn');
add_action('wp_ajax_nopriv_dgptm_get_active_poll_info','dgptm_get_active_poll_info_fn');
function dgptm_get_active_poll_info_fn(){
    global $wpdb;
    $poll=$wpdb->get_row("SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_polls WHERE status='active' LIMIT 1");
    if(!$poll){ wp_send_json_success(array('active_poll'=>null)); }
    $question=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_poll_questions WHERE poll_id=%d AND status='active' LIMIT 1",$poll->id));
    $resp=array('active_poll'=>array('id'=>$poll->id,'name'=>$poll->name,'logo_url'=>$poll->logo_url),'active_question'=>null);
    if($question) $resp['active_question']=array('id'=>$question->id,'question'=>$question->question);
    wp_send_json_success($resp);
}

add_action('wp_ajax_dgptm_is_poll_active','dgptm_is_poll_active_fn');
add_action('wp_ajax_nopriv_dgptm_is_poll_active','dgptm_is_poll_active_fn');
function dgptm_is_poll_active_fn(){
    global $wpdb;
    $c=$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}dgptm_abstimmung_polls WHERE status='active'");
    wp_send_json_success(array('active'=>($c>0)));
}

// Name gate: register anon participant
add_action('wp_ajax_dgptm_register_anon_participant','dgptm_register_anon_participant_fn');
add_action('wp_ajax_nopriv_dgptm_register_anon_participant','dgptm_register_anon_participant_fn');
function dgptm_register_anon_participant_fn(){
    global $wpdb;
    $name = sanitize_text_field(isset($_POST['name']) ? $_POST['name'] : '');
    $pid  = intval(isset($_POST['poll_id']) ? $_POST['poll_id'] : 0);
    if($name==='') wp_send_json_error('Name fehlt.');
    $poll=null;
    if($pid){
        $poll=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_polls WHERE id=%d",$pid));
        if(!$poll || $poll->status!=='active') $poll=null;
    }
    if(!$poll){
        $poll=$wpdb->get_row("SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_polls WHERE status='active' LIMIT 1");
    }
    if(!$poll) wp_send_json_error('Keine aktive Umfrage.');

    if(empty($_COOKIE[DGPTMVOTE_COOKIE]) && !empty($_COOKIE['dgptm_voteid'])){
        setcookie(DGPTMVOTE_COOKIE, sanitize_text_field($_COOKIE['dgptm_voteid']), time()+3600*24*365, '/');
        $_COOKIE[DGPTMVOTE_COOKIE] = sanitize_text_field($_COOKIE['dgptm_voteid']);
    }
    $cn=DGPTMVOTE_COOKIE;
    if(empty($_COOKIE[$cn])){
        $rand='anon_'.wp_generate_uuid4();
        setcookie($cn, $rand, time()+3600*24*365, '/');
        $_COOKIE[$cn]=$rand;
    }
    $cookie_id=sanitize_text_field($_COOKIE[$cn]);

    $tbl=$wpdb->prefix.'dgptm_abstimmung_participants';
    $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tbl WHERE poll_id=%d AND cookie_id=%s",$poll->id,$cookie_id));
    if(!$exists){
        $wpdb->insert($tbl,array(
            'poll_id'=>$poll->id,
            'user_id'=>0,
            'fullname'=>$name,
            'cookie_id'=>$cookie_id,
            'joined_time'=>current_time('mysql'),
            'source'=>'namegate'
        ));
    }
    wp_send_json_success('Registriert.');
}
