<?php
if (!defined('ABSPATH')) exit;

// ====== SETTINGS MODAL ======
add_action('wp_ajax_dgptm_get_manager_settings','dgptm_get_manager_settings_fn');
function dgptm_get_manager_settings_fn(){
    if(!dgptm_is_manager()) wp_send_json_error('Keine Rechte.');
    // Settings are now inline in the manager dashboard.
    // This endpoint is kept for backwards compatibility.
    $beamer_state = dgptm_get_beamer_state();
    ob_start(); ?>
    <div style="font-size:13px;">
      <p style="color:#64748b;">Einstellungen sind jetzt direkt im Manager-Dashboard integriert.</p>
      <p>Beamer-Status: <strong><?php echo esc_html($beamer_state['mode'] ?? 'auto'); ?></strong> · QR: <?php echo !empty($beamer_state['qr_visible']) ? 'Ein' : 'Aus'; ?></p>
    </div>
    <?php
    $html = ob_get_clean();
    wp_send_json_success(array('html'=>$html));
}

add_action('wp_ajax_dgptm_save_beamer_settings','dgptm_save_beamer_settings_fn');
function dgptm_save_beamer_settings_fn(){
    if(!dgptm_is_manager()) wp_send_json_error('Keine Rechte.');
    update_option('dgptm_no_poll_text', isset($_POST['dgptm_no_poll_text'])?wp_kses_post($_POST['dgptm_no_poll_text']):'');
    wp_send_json_success();
}

add_action('wp_ajax_dgptm_toggle_beamer_qr','dgptm_toggle_beamer_qr_fn');
function dgptm_toggle_beamer_qr_fn(){
    if(!dgptm_is_manager()) wp_send_json_error('Keine Rechte.');
    $visible = !empty($_POST['visible']) ? 1 : 0;
    $state = dgptm_get_beamer_state();
    $state['qr_visible'] = $visible ? true : false;
    update_option('dgptm_beamer_state', wp_json_encode($state));
    wp_send_json_success();
}

add_action('wp_ajax_dgptm_set_beamer_state','dgptm_set_beamer_state_fn');
function dgptm_set_beamer_state_fn(){
    if(!dgptm_is_manager()) wp_send_json_error('Keine Rechte.');
    $mode = sanitize_text_field(isset($_POST['mode'])?$_POST['mode']:'auto');
    $st = dgptm_get_beamer_state();
    $qr_keep = !empty($st['qr_visible']);
    $st = array('mode'=>$mode, 'qr_visible'=>$qr_keep);

    global $wpdb;

    if($mode==='results_one'){
        if(empty($_POST['question_id']) || empty($_POST['poll_id'])) wp_send_json_error('Fehlende IDs.');
        $qid = intval($_POST['question_id']);
        $q = $wpdb->get_row($wpdb->prepare("SELECT results_released,status FROM {$wpdb->prefix}dgptm_abstimmung_poll_questions WHERE id=%d",$qid));
        if(!$q) wp_send_json_error('Frage nicht gefunden.');
        if($q->status==='active') wp_send_json_error('Während der laufenden Abstimmung wird keine Statistik angezeigt.');
        if(!(int)$q->results_released) wp_send_json_error('Ergebnis ist nicht freigegeben.');
        $st['poll_id'] = intval($_POST['poll_id']);
        $st['question_id'] = $qid;
    }
    if($mode==='results_all'){
        if(empty($_POST['poll_id'])) wp_send_json_error('poll_id fehlt.');
        $pid = intval($_POST['poll_id']);
        $count = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}dgptm_abstimmung_poll_questions WHERE poll_id=%d AND status='stopped' AND results_released=1",$pid));
        if($count===0){
            wp_send_json_error('Es gibt keine freigegebenen, abgeschlossenen Fragen in dieser Umfrage.');
        }
        $st['poll_id'] = $pid;
    }

    update_option('dgptm_beamer_state', wp_json_encode($st));
    wp_send_json_success();
}

add_action('wp_ajax_dgptm_set_beamer_state_overall','dgptm_set_beamer_state_overall_fn');
function dgptm_set_beamer_state_overall_fn(){
    if(!dgptm_is_manager()) wp_send_json_error('Keine Rechte.');
    global $wpdb;
    $poll=$wpdb->get_row("SELECT id FROM {$wpdb->prefix}dgptm_abstimmung_polls WHERE status='active' LIMIT 1");
    if(!$poll) wp_send_json_error('Keine aktive Umfrage.');
    $cnt=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}dgptm_abstimmung_poll_questions WHERE poll_id=%d AND status='stopped' AND results_released=1 AND in_overall=1",$poll->id));
    if($cnt<=0) wp_send_json_error('Keine freigegebenen, markierten Fragen vorhanden.');
    $state = dgptm_get_beamer_state();
    $state['mode']='results_all';
    $state['poll_id']=$poll->id;
    update_option('dgptm_beamer_state', wp_json_encode($state));
    wp_send_json_success();
}

// ====== POLL LIST + DETAILS ======
add_action('wp_ajax_dgptm_get_poll_details','dgptm_get_poll_details_fn');
function dgptm_get_poll_details_fn(){
    if(!dgptm_is_manager()) wp_send_json_error('Keine Rechte.');
    if(empty($_POST['poll_id'])) wp_send_json_error('poll_id fehlt.');
    global $wpdb;
    $pid = intval($_POST['poll_id']);
    $poll=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_polls WHERE id=%d",$pid));
    if(!$poll) wp_send_json_error('Umfrage nicht gefunden.');

    // Auto-close check for active questions
    $active_q = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_poll_questions WHERE poll_id = %d AND status = 'active' LIMIT 1",
        $pid
    ));
    if ($active_q && function_exists('dgptm_check_auto_close')) {
        dgptm_check_auto_close($active_q);
    }

    ob_start(); ?>
    <div class="mp-detail">
      <!-- Meta kompakt -->
      <div style="display:flex;gap:12px;flex-wrap:wrap;font-size:11px;color:var(--mp-gray,#64748b);margin-bottom:8px;">
        <span>Anmeldung: <strong><?php echo $poll->requires_signup ? 'Ja' : 'Nein'; ?></strong></span>
        <?php if (!empty($poll->logo_url) && dgptm_is_image_ext($poll->logo_url)): ?>
          <span>Logo: <img src="<?php echo esc_url($poll->logo_url); ?>" alt="" style="height:18px;vertical-align:middle;border-radius:3px;"></span>
        <?php endif; ?>
      </div>

      <!-- QR-Code (versteckt, bei Bedarf einblenden) -->
      <?php $qrUrl = add_query_arg(array('dgptm_member'=>1,'poll_id'=>$poll->id), home_url('/')); ?>
      <div style="margin-bottom:6px;">
        <button class="mp-btn mp-btn-xs mp-qr-toggle" data-pid="<?php echo (int)$poll->id; ?>">QR-Code</button>
        <code id="qrUrlText_<?php echo (int)$poll->id; ?>" data-qr="<?php echo esc_url($qrUrl); ?>" style="font-size:10px;color:var(--mp-gray,#64748b);"><?php echo esc_html($qrUrl); ?></code>
      </div>
      <div id="qrSection_<?php echo (int)$poll->id; ?>" class="mp-qr">
        <canvas id="qrCanvas_<?php echo (int)$poll->id; ?>" width="180" height="180"></canvas>
        <div style="margin-top:4px;"><button class="mp-btn mp-btn-xs" onclick="dgptmDownloadQR(<?php echo (int)$poll->id; ?>)">PNG herunterladen</button></div>
      </div>

      <!-- Beamer-Inhalt -->
      <div class="mp-panel" style="margin:6px 0;">
        <div class="mp-panel-head" onclick="this.classList.toggle('open');this.nextElementSibling.classList.toggle('open');">
          <span class="arrow">▶</span> Beamer Pause-Inhalt (HTML)
        </div>
        <div class="mp-panel-body">
          <textarea id="beamerContent_<?php echo (int)$poll->id; ?>" rows="2" style="width:100%;font-size:12px;"><?php echo esc_textarea(isset($poll->beamer_content) ? $poll->beamer_content : ''); ?></textarea>
          <div style="margin-top:4px;display:flex;gap:6px;align-items:center;font-size:12px;">
            <label class="mp-sw"><input type="checkbox" class="beamerContentToggle" data-pid="<?php echo (int)$poll->id; ?>" <?php checked(!empty($poll->beamer_content_active)); ?>><span></span></label>
            <span>Anzeigen</span>
            <button class="mp-btn mp-btn-xs saveBeamerContentBtn" data-pid="<?php echo (int)$poll->id; ?>">Speichern</button>
            <span style="color:var(--mp-gray,#64748b);font-size:10px;">HTML erlaubt: &lt;b&gt;, &lt;img&gt;, &lt;br&gt;, etc.</span>
          </div>
        </div>
      </div>

      <!-- Fragen -->
      <h4 style="margin:8px 0 4px;">Fragen</h4>
      <?php
        $questions = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_poll_questions WHERE poll_id=%d ORDER BY created ASC",$poll->id));
        if ($questions):
          foreach ($questions as $q):
            $choices = json_decode($q->choices, true); if (!is_array($choices)) $choices = array();
            $choicesStr = implode(', ', $choices);
            $current_display = isset($q->display_type) ? $q->display_type : 'cards';
            $current_images = isset($q->choice_images) ? $q->choice_images : '';
            $statusBadge = 'mp-badge-' . $q->status;
      ?>
        <div class="mp-qcard">
          <div class="mp-qcard-head">
            <div>
              <div class="mp-qcard-q"><?php echo esc_html($q->question); ?></div>
              <div class="mp-qcard-meta">
                <span class="mp-badge <?php echo $statusBadge; ?>"><?php echo esc_html($q->status); ?></span>
                · <?php echo (int)$q->max_votes; ?> Stimmen
                <?php if ($q->is_anonymous): ?> · Anonym<?php endif; ?>
                <?php if ((int)$q->time_limit > 0): ?> · <?php echo (int)$q->time_limit; ?>s<?php if ($q->auto_close): ?> (auto)<?php endif; ?><?php endif; ?>
                <?php
                  $majLabels = array('simple'=>'>50%','two_thirds'=>'⅔','absolute'=>'absolut');
                  if (isset($q->majority_type) && $q->majority_type !== 'simple'):
                ?> · <?php echo $majLabels[$q->majority_type] ?? $q->majority_type; ?><?php endif; ?>
                <?php if ((int)($q->quorum ?? 0) > 0): ?> · Quorum <?php echo (int)$q->quorum; ?><?php endif; ?>
              </div>
            </div>
            <div style="display:flex;gap:4px;align-items:center;flex-shrink:0;">
              <label class="mp-sw" title="Ergebnis freigeben"><input type="checkbox" class="resultReleaseSwitch" data-qid="<?php echo (int)$q->id; ?>" <?php checked($q->results_released); ?>><span></span></label>
              <span style="font-size:10px;color:var(--mp-gray,#64748b);">Frei</span>
              <label class="mp-sw" title="In Gesamtstatistik"><input type="checkbox" class="overallSwitch" data-qid="<?php echo (int)$q->id; ?>" <?php checked(!empty($q->in_overall)); ?>><span></span></label>
              <span style="font-size:10px;color:var(--mp-gray,#64748b);">Gesamt</span>
            </div>
          </div>
          <div class="mp-qcard-actions">
            <?php if ($q->status == 'prepared'): ?>
              <button class="questionCtrlBtn mp-btn mp-btn-g mp-btn-xs" data-action="dgptm_activate_poll_question" data-qid="<?php echo (int)$q->id; ?>">▶ Start</button>
            <?php elseif ($q->status == 'active'): ?>
              <button class="questionCtrlBtn mp-btn mp-btn-d" data-action="dgptm_stop_poll_question" data-qid="<?php echo (int)$q->id; ?>">■ Stop</button>
            <?php elseif ($q->status == 'stopped'): ?>
              <button class="questionCtrlBtn mp-btn mp-btn-xs" data-action="dgptm_prepare_poll_question" data-qid="<?php echo (int)$q->id; ?>">↻ Reset</button>
            <?php endif; ?>
            <label class="mp-sw" title="Im Beamer zeigen"><input type="checkbox" class="beamerDisplaySwitch" data-qid="<?php echo (int)$q->id; ?>" data-pid="<?php echo (int)$poll->id; ?>"><span></span></label>
            <span style="font-size:10px;color:var(--mp-gray,#64748b);">Beamer</span>
            <form class="updateQuestionForm" data-qid="<?php echo (int)$q->id; ?>" style="display:inline-flex;gap:4px;align-items:center;margin:0;">
              <input type="hidden" name="question_text" value="<?php echo esc_attr($q->question); ?>">
              <input type="hidden" name="question_choices" value="<?php echo esc_attr($choicesStr); ?>">
              <input type="hidden" name="max_votes" value="<?php echo (int)$q->max_votes; ?>">
              <input type="hidden" name="is_anonymous" value="<?php echo $q->is_anonymous ? 1 : 0; ?>">
              <select name="display_type" style="font-size:11px;padding:2px 4px;">
                <option value="cards" <?php selected($current_display,'cards'); ?>>Karten</option>
                <option value="horizontal_bars" <?php selected($current_display,'horizontal_bars'); ?>>Balken</option>
                <option value="vertical_bars" <?php selected($current_display,'vertical_bars'); ?>>Säulen</option>
                <option value="pie" <?php selected($current_display,'pie'); ?>>Kuchen</option>
              </select>
              <button type="submit" class="mp-btn mp-btn-xs">OK</button>
            </form>
            <button class="showVotesBtn mp-btn mp-btn-xs" data-qid="<?php echo (int)$q->id; ?>">Stimmen</button>
            <button class="deleteQuestionBtn mp-btn mp-btn-d" data-qid="<?php echo (int)$q->id; ?>">×</button>
          </div>
          <div id="votesArea_<?php echo (int)$q->id; ?>" style="display:none;"></div>
        </div>
      <?php endforeach; else: ?>
        <div style="color:var(--mp-gray,#64748b);font-size:12px;padding:4px 0;">Noch keine Fragen</div>
      <?php endif; ?>

      <!-- Neue Frage -->
      <div class="mp-panel" style="margin-top:8px;">
        <div class="mp-panel-head" onclick="this.classList.toggle('open');this.nextElementSibling.classList.toggle('open');">
          <span class="arrow">▶</span> Neue Frage anlegen
        </div>
        <div class="mp-panel-body">
          <form class="addQuestionForm">
            <input type="hidden" name="poll_id" value="<?php echo (int)$poll->id; ?>">
            <div class="mp-row"><label>Frage:</label><input type="text" name="question_text" required style="flex:1;min-width:200px;"></div>
            <div class="mp-row"><label>Antworten (kommagetrennt):</label><input type="text" name="question_choices" style="flex:1;min-width:200px;"></div>
            <div class="mp-row">
              <label>Max:</label><input type="number" name="max_votes" value="1" style="width:50px;">
              <label class="mp-sw"><input type="checkbox" name="is_anonymous"><span></span></label><label>Anonym</label>
              <label>Darstellung:</label>
              <select name="display_type" style="font-size:12px;">
                <option value="cards">Karten</option>
                <option value="horizontal_bars">Balken</option>
                <option value="vertical_bars">Säulen</option>
                <option value="pie">Kuchen</option>
              </select>
            </div>
            <div class="mp-row">
              <label>Timer (s):</label><input type="number" name="time_limit" value="0" min="0" style="width:60px;">
              <label class="mp-sw"><input type="checkbox" name="auto_close"><span></span></label><label>Auto-Close</label>
              <label>Mehrheit:</label>
              <select name="majority_type" style="font-size:12px;">
                <option value="simple">&gt;50%</option>
                <option value="two_thirds">⅔</option>
                <option value="absolute">Absolut</option>
              </select>
              <label>Quorum:</label><input type="number" name="quorum" value="0" min="0" style="width:50px;">
            </div>
            <div class="mp-row"><label>Bilder-URLs (optional):</label><input type="text" name="choice_images" placeholder="url1, url2, ..." style="flex:1;"></div>
            <div class="mp-row"><button type="submit" class="mp-btn mp-btn-p">Anlegen</button></div>
          </form>
        </div>
      </div>

      <!-- Teilnehmer & Export -->
      <div style="display:flex;gap:4px;flex-wrap:wrap;margin-top:8px;">
        <button class="showParticipantsBtn mp-btn mp-btn-xs" data-pid="<?php echo (int)$poll->id; ?>">Teilnehmer</button>
        <a class="mp-btn mp-btn-xs" href="<?php echo esc_url(admin_url('admin-ajax.php?action=dgptm_export_poll_complete&poll_id='.(int)$poll->id.'&format=csv')); ?>" target="_blank">CSV</a>
        <a class="mp-btn mp-btn-xs" href="<?php echo esc_url(admin_url('admin-ajax.php?action=dgptm_export_poll_complete&poll_id='.(int)$poll->id.'&format=pdf')); ?>" target="_blank">PDF</a>
      </div>
      <div id="participantsArea_<?php echo (int)$poll->id; ?>" style="display:none;"></div>
    </div>
    <?php
    $html = ob_get_clean();
    wp_send_json_success(array('html'=>$html));
}

// ===== POLL CRUD =====
add_action('wp_ajax_dgptm_create_poll','dgptm_create_poll_fn');
function dgptm_create_poll_fn(){
    if(!dgptm_is_manager()) wp_send_json_error('Keine Rechte.');
    if(empty($_POST['poll_name'])) wp_send_json_error('Name fehlt.');
    global $wpdb;
    $guest_voting = isset($_POST['guest_voting']) ? 1 : 0;
    $beamer_content = isset($_POST['beamer_content']) ? wp_kses_post($_POST['beamer_content']) : '';
    $beamer_content_active = !empty($_POST['beamer_content_active']) ? 1 : 0;
    $wpdb->insert($wpdb->prefix.'dgptm_abstimmung_polls',array(
        'name'=>sanitize_text_field($_POST['poll_name']),
        'status'=>'prepared',
        'created'=>current_time('mysql'),
        'requires_signup'=>!empty($_POST['requires_signup'])?1:0,
        'time_limit'=>0,
        'logo_url'=>isset($_POST['poll_logo_url'])?sanitize_text_field($_POST['poll_logo_url']):'',
        'guest_voting'=>$guest_voting,
        'beamer_content'=>$beamer_content,
        'beamer_content_active'=>$beamer_content_active
    ));
    $wpdb->insert_id ? wp_send_json_success('Umfrage angelegt.') : wp_send_json_error('Fehler beim Anlegen der Umfrage.');
}

add_action('wp_ajax_dgptm_toggle_poll_status','dgptm_toggle_poll_status_fn');
function dgptm_toggle_poll_status_fn(){
    if(!dgptm_is_manager()) wp_send_json_error('Keine Rechte.');
    if(empty($_POST['poll_id'])) wp_send_json_error('Keine poll_id.');
    global $wpdb;
    $pid=intval($_POST['poll_id']);
    $poll=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_polls WHERE id=%d",$pid));
    if(!$poll) wp_send_json_error('Umfrage nicht gefunden.');
    $new = (strtolower($poll->status)==='active')?'archived':'active';
    if($new==='active'){ $wpdb->query("UPDATE {$wpdb->prefix}dgptm_abstimmung_polls SET status='prepared' WHERE status='active'"); }
    $wpdb->update($wpdb->prefix.'dgptm_abstimmung_polls',array('status'=>$new),array('id'=>$pid));
    if($new==='archived'){
        $wpdb->update($wpdb->prefix.'dgptm_abstimmung_poll_questions',
            array('status'=>'stopped','ended'=>current_time('mysql')),
            array('poll_id'=>$pid,'status'=>'active')
        );
    }
    wp_send_json_success(array('new_status'=>$new));
}

add_action('wp_ajax_dgptm_toggle_beamer_all_for_poll','dgptm_toggle_beamer_all_for_poll_fn');
function dgptm_toggle_beamer_all_for_poll_fn(){
    if(!dgptm_is_manager()) wp_send_json_error('Keine Rechte.');
    if(empty($_POST['poll_id'])) wp_send_json_error('poll_id fehlt.');
    global $wpdb;
    $pid=intval($_POST['poll_id']);
    $state = dgptm_get_beamer_state();
    if(isset($state['mode']) && $state['mode']==='results_all' && !empty($state['poll_id']) && (int)$state['poll_id']===$pid){
        update_option('dgptm_beamer_state', wp_json_encode(array('mode'=>'auto','qr_visible'=>!empty($state['qr_visible']))));
        wp_send_json_success(array('state'=>'off'));
    }
    $cnt = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}dgptm_abstimmung_poll_questions WHERE poll_id=%d AND status='stopped' AND results_released=1",$pid));
    if($cnt<=0) wp_send_json_error('Keine freigegebenen, beendeten Fragen vorhanden.');
    $state['mode']='results_all';
    $state['poll_id']=$pid;
    update_option('dgptm_beamer_state', wp_json_encode($state));
    wp_send_json_success(array('state'=>'on'));
}

// ===== QUESTIONS CRUD =====
add_action('wp_ajax_dgptm_add_poll_question','dgptm_add_poll_question_fn');
function dgptm_add_poll_question_fn(){
    if(!dgptm_is_manager()) wp_send_json_error('Keine Rechte.');
    if(empty($_POST['poll_id']) || empty($_POST['question_text'])) wp_send_json_error('poll_id oder Fragetext fehlt.');
    global $wpdb;
    $pid=intval($_POST['poll_id']);
    $choices = isset($_POST['question_choices']) ? array_map('trim', explode(',', $_POST['question_choices'])) : array();
    $display_type = isset($_POST['display_type']) ? sanitize_text_field($_POST['display_type']) : 'cards';
    if (!in_array($display_type, array('cards', 'horizontal_bars', 'vertical_bars', 'pie'), true)) {
        $display_type = 'cards';
    }
    // Backwards compatibility: map display_type to chart_type
    $chart_type = 'bar';
    if ($display_type === 'horizontal_bars') $chart_type = 'bar';
    elseif ($display_type === 'vertical_bars') $chart_type = 'pie';

    // Parse choice_images
    $choice_images_raw = isset($_POST['choice_images']) ? sanitize_text_field($_POST['choice_images']) : '';
    $choice_images_arr = array();
    if (!empty($choice_images_raw)) {
        $choice_images_arr = array_map('trim', explode(',', $choice_images_raw));
        $choice_images_arr = array_map('esc_url_raw', $choice_images_arr);
        $choice_images_arr = array_filter($choice_images_arr);
    }
    $choice_images_json = !empty($choice_images_arr) ? wp_json_encode(array_values($choice_images_arr)) : null;

    $time_limit    = isset($_POST['time_limit']) ? absint($_POST['time_limit']) : 0;
    $auto_close    = !empty($_POST['auto_close']) ? 1 : 0;
    $majority_type = isset($_POST['majority_type']) ? sanitize_text_field($_POST['majority_type']) : 'simple';
    $quorum        = isset($_POST['quorum']) ? absint($_POST['quorum']) : 0;

    // Validate majority_type
    if (!in_array($majority_type, array('simple', 'two_thirds', 'absolute'), true)) {
        $majority_type = 'simple';
    }

    $res = $wpdb->insert($wpdb->prefix.'dgptm_abstimmung_poll_questions',array(
        'poll_id'=>$pid,
        'question'=>sanitize_text_field($_POST['question_text']),
        'choices'=>wp_json_encode($choices),
        'max_votes'=>isset($_POST['max_votes'])?intval($_POST['max_votes']):1,
        'status'=>'prepared',
        'created'=>current_time('mysql'),
        'time_limit'=>$time_limit,
        'max_choices'=>0,
        'is_repeatable'=>1,
        'is_anonymous'=>!empty($_POST['is_anonymous'])?1:0,
        'chart_type'=>$chart_type,
        'display_type'=>$display_type,
        'choice_images'=>$choice_images_json,
        'in_overall'=>0,
        'auto_close'=>$auto_close,
        'majority_type'=>$majority_type,
        'quorum'=>$quorum
    ));
    $wpdb->insert_id ? wp_send_json_success('Frage angelegt.') : wp_send_json_error('Fehler beim Anlegen der Frage.');
}

add_action('wp_ajax_dgptm_delete_poll_question','dgptm_delete_poll_question_fn');
function dgptm_delete_poll_question_fn(){
    if(!dgptm_is_manager()) wp_send_json_error('Keine Rechte.');
    if(empty($_POST['question_id'])) wp_send_json_error('Keine question_id.');
    global $wpdb; $qid=intval($_POST['question_id']);
    $wpdb->delete($wpdb->prefix.'dgptm_abstimmung_votes',array('question_id'=>$qid));
    $del=$wpdb->delete($wpdb->prefix.'dgptm_abstimmung_poll_questions',array('id'=>$qid));
    ($del!==false)?wp_send_json_success('Frage gelöscht.'):wp_send_json_error('Fehler beim Löschen.');
}

add_action('wp_ajax_dgptm_update_poll_question','dgptm_update_poll_question_fn');
function dgptm_update_poll_question_fn(){
    if(!dgptm_is_manager()) wp_send_json_error('Keine Rechte.');
    if(empty($_POST['question_id']) || empty($_POST['question_text'])) wp_send_json_error('Frage-ID oder Fragetext fehlt.');
    global $wpdb; $qid=intval($_POST['question_id']);
    $choices = isset($_POST['question_choices']) ? array_map('trim', explode(',', $_POST['question_choices'])) : array();

    $display_type = isset($_POST['display_type']) ? sanitize_text_field($_POST['display_type']) : 'cards';
    if (!in_array($display_type, array('cards', 'horizontal_bars', 'vertical_bars', 'pie'), true)) {
        $display_type = 'cards';
    }
    // Backwards compatibility: map display_type to chart_type
    $chart_type = 'bar';
    if ($display_type === 'horizontal_bars') $chart_type = 'bar';
    elseif ($display_type === 'vertical_bars') $chart_type = 'pie';

    // Parse choice_images
    $choice_images_raw = isset($_POST['choice_images']) ? sanitize_text_field($_POST['choice_images']) : '';
    $choice_images_arr = array();
    if (!empty($choice_images_raw)) {
        $choice_images_arr = array_map('trim', explode(',', $choice_images_raw));
        $choice_images_arr = array_map('esc_url_raw', $choice_images_arr);
        $choice_images_arr = array_filter($choice_images_arr);
    }
    $choice_images_json = !empty($choice_images_arr) ? wp_json_encode(array_values($choice_images_arr)) : null;

    $time_limit    = isset($_POST['time_limit']) ? absint($_POST['time_limit']) : 0;
    $auto_close    = !empty($_POST['auto_close']) ? 1 : 0;
    $majority_type = isset($_POST['majority_type']) ? sanitize_text_field($_POST['majority_type']) : 'simple';
    $quorum        = isset($_POST['quorum']) ? absint($_POST['quorum']) : 0;

    // Validate majority_type
    if (!in_array($majority_type, array('simple', 'two_thirds', 'absolute'), true)) {
        $majority_type = 'simple';
    }

    $res=$wpdb->update($wpdb->prefix.'dgptm_abstimmung_poll_questions',array(
        'question'=>sanitize_text_field($_POST['question_text']),
        'choices'=>wp_json_encode($choices),
        'max_votes'=>isset($_POST['max_votes'])?intval($_POST['max_votes']):1,
        'is_anonymous'=>!empty($_POST['is_anonymous'])?1:0,
        'chart_type'=>$chart_type,
        'display_type'=>$display_type,
        'choice_images'=>$choice_images_json,
        'time_limit'=>$time_limit,
        'auto_close'=>$auto_close,
        'majority_type'=>$majority_type,
        'quorum'=>$quorum
    ),array('id'=>$qid));
    ($res!==false)?wp_send_json_success('Frage aktualisiert.'):wp_send_json_error('Fehler beim Aktualisieren.');
}

add_action('wp_ajax_dgptm_prepare_poll_question','dgptm_prepare_poll_question_fn');
function dgptm_prepare_poll_question_fn(){
    if(!dgptm_is_manager()) wp_send_json_error('Keine Rechte.');
    if(empty($_POST['question_id'])) wp_send_json_error('Keine question_id.');
    global $wpdb; $qid=intval($_POST['question_id']);
    $res=$wpdb->update($wpdb->prefix.'dgptm_abstimmung_poll_questions',array('status'=>'prepared','ended'=>null),array('id'=>$qid));
    ($res!==false)?wp_send_json_success('Frage zurückgesetzt.'):wp_send_json_error('Fehler beim Zurücksetzen.');
}

add_action('wp_ajax_dgptm_activate_poll_question','dgptm_activate_poll_question_fn');
function dgptm_activate_poll_question_fn(){
    if(!dgptm_is_manager()) wp_send_json_error('Keine Rechte.');
    if(empty($_POST['question_id'])) wp_send_json_error('Keine question_id.');
    global $wpdb; $qid=intval($_POST['question_id']);
    $q=$wpdb->get_row($wpdb->prepare("SELECT poll_id FROM {$wpdb->prefix}dgptm_abstimmung_poll_questions WHERE id=%d",$qid));
    if(!$q) wp_send_json_error('Frage nicht gefunden.');
    $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}dgptm_abstimmung_poll_questions SET status='stopped', ended=%s WHERE poll_id=%d AND status='active'", current_time('mysql'), $q->poll_id));
    $res=$wpdb->update($wpdb->prefix.'dgptm_abstimmung_poll_questions',array(
        'status'=>'active',
        'created'=>current_time('mysql'),
        'started_at'=>current_time('mysql'),
        'ended'=>null
    ),array('id'=>$qid));
    ($res!==false)?wp_send_json_success('Frage aktiviert.'):wp_send_json_error('Fehler beim Aktivieren.');
}

add_action('wp_ajax_dgptm_stop_poll_question','dgptm_stop_poll_question_fn');
function dgptm_stop_poll_question_fn(){
    if(!dgptm_is_manager()) wp_send_json_error('Keine Rechte.');
    if(empty($_POST['question_id'])) wp_send_json_error('Keine question_id.');
    global $wpdb; $qid=intval($_POST['question_id']);
    $res=$wpdb->update($wpdb->prefix.'dgptm_abstimmung_poll_questions',array('status'=>'stopped','ended'=>current_time('mysql')),array('id'=>$qid));
    ($res!==false)?wp_send_json_success('Frage geschlossen.'):wp_send_json_error('Fehler beim Schließen.');
}

add_action('wp_ajax_dgptm_release_poll_results','dgptm_release_poll_results_fn');
function dgptm_release_poll_results_fn(){
    if(!dgptm_is_manager()) wp_send_json_error('Keine Rechte.');
    if(empty($_POST['question_id'])) wp_send_json_error('Keine question_id.');
    global $wpdb; $qid=intval($_POST['question_id']);
    $row=$wpdb->get_row($wpdb->prepare("SELECT results_released FROM {$wpdb->prefix}dgptm_abstimmung_poll_questions WHERE id=%d",$qid));
    if(!$row) wp_send_json_error('Frage nicht gefunden.');
    $new = $row->results_released?0:1;
    $res=$wpdb->update($wpdb->prefix.'dgptm_abstimmung_poll_questions',array('results_released'=>$new),array('id'=>$qid));
    ($res!==false)?wp_send_json_success('Ergebnisfreigabe geändert.'):wp_send_json_error('Fehler beim Ergebnis-Toggle.');
}

add_action('wp_ajax_dgptm_toggle_in_overall','dgptm_toggle_in_overall_fn');
function dgptm_toggle_in_overall_fn(){
    if(!dgptm_is_manager()) wp_send_json_error('Keine Rechte.');
    if(empty($_POST['question_id'])) wp_send_json_error('Keine question_id.');
    global $wpdb; $qid=intval($_POST['question_id']);
    $row=$wpdb->get_row($wpdb->prepare("SELECT in_overall FROM {$wpdb->prefix}dgptm_abstimmung_poll_questions WHERE id=%d",$qid));
    if(!$row) wp_send_json_error('Frage nicht gefunden.');
    $new = $row->in_overall?0:1;
    $res=$wpdb->update($wpdb->prefix.'dgptm_abstimmung_poll_questions',array('in_overall'=>$new),array('id'=>$qid));
    ($res!==false)?wp_send_json_success('Gesamtstatistik-Toggle geändert.'):wp_send_json_error('Fehler beim Toggle.');
}

// ===== Participants & Votes =====
add_action('wp_ajax_dgptm_get_poll_participants','dgptm_get_poll_participants_fn');
function dgptm_get_poll_participants_fn(){
    if(!dgptm_is_manager()) wp_send_json_error('Keine Rechte.');
    if(empty($_POST['poll_id'])) wp_send_json_error('Keine poll_id.');
    global $wpdb; $pid=intval($_POST['poll_id']);
    $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_participants WHERE poll_id=%d ORDER BY joined_time ASC",$pid),ARRAY_A);
    if(!$rows){ wp_send_json_success(array('html'=>'<p>Keine Teilnehmer.</p>')); }
    $out='<div style="overflow:auto;"><table border="1" cellpadding="5" style="border-collapse:collapse;min-width:880px;"><tr><th>User ID</th><th>Vollname</th><th>Vorname</th><th>Nachname</th><th>MitgliedsNr</th><th>Status</th><th>E-Mail</th><th>Cookie-ID</th><th>Token</th><th>Quelle</th><th>Beitrittszeit</th></tr>';
    foreach($rows as $r){
        $out.='<tr><td>'.$r['user_id'].'</td><td>'.esc_html($r['fullname']).'</td><td>'.esc_html($r['first_name']).'</td><td>'.esc_html($r['last_name']).'</td><td>'.esc_html($r['member_no']).'</td><td>'.esc_html($r['member_status']).'</td><td>'.esc_html($r['email']).'</td><td>'.esc_html($r['cookie_id']).'</td><td>'.esc_html($r['token']).'</td><td>'.esc_html($r['source']).'</td><td>'.$r['joined_time'].'</td></tr>';
    }
    $out.='</table></div>';
    wp_send_json_success(array('html'=>$out));
}

add_action('wp_ajax_dgptm_list_votes','dgptm_list_votes_fn');
function dgptm_list_votes_fn(){
    if(!dgptm_is_manager()) wp_send_json_error('Keine Rechte.');
    if(empty($_POST['question_id'])) wp_send_json_error('Keine question_id.');
    global $wpdb; $qid=intval($_POST['question_id']);
    $q=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_poll_questions WHERE id=%d",$qid));
    if(!$q) wp_send_json_error('Frage nicht gefunden.');
    $poll_id=(int)$q->poll_id;

    $rows=$wpdb->get_results($wpdb->prepare("
      SELECT v.* FROM {$wpdb->prefix}dgptm_abstimmung_votes v
      WHERE v.question_id=%d
      ORDER BY v.vote_time ASC
    ",$qid));

    $choices=json_decode($q->choices,true); if(!is_array($choices)) $choices=array();

    $html = "<div><h5>Stimmen zu: ".esc_html($q->question)."</h5>";
    $html.= "<table class='votes-table' style='width:100%;border-collapse:collapse;'><thead><tr><th>ID</th><th>User/Name</th><th>Antwort</th><th>Zeit</th><th>Status</th><th>Aktion</th></tr></thead><tbody>";

    foreach($rows as $r){
        $name='Anonym';
        if(!$q->is_anonymous){
            if($r->user_id>0){
                $ud=get_userdata($r->user_id);
                if($ud) $name=$ud->display_name;
            } else {
                $cookie_id=str_replace('COOKIE:','',$r->ip);
                $p=$wpdb->get_row($wpdb->prepare("SELECT fullname FROM {$wpdb->prefix}dgptm_abstimmung_participants WHERE poll_id=%d AND cookie_id=%s",$poll_id,$cookie_id));
                if($p && $p->fullname) $name=$p->fullname;
                else $name='Gast';
            }
        }
        $ans = isset($choices[$r->choice_index]) ? $choices[$r->choice_index] : ('Index '.$r->choice_index);
        $status = $r->is_invalid? "<span style='color:#a00'>ungültig</span>" : "<span style='color:#080'>gültig</span>";
        $btnTxt = $r->is_invalid? "Wieder gültig" : "Ungültig";
        $html.="<tr><td>".$r->id."</td><td>".esc_html($name)."</td><td>".esc_html($ans)."</td><td>".$r->vote_time."</td><td>".$status."</td><td><button class='toggleInvalidBtn btn' style='padding:6px 10px' data-vid='".$r->id."' data-qid='".$qid."'>".$btnTxt."</button></td></tr>";
    }
    $html.="</tbody></table></div>";
    wp_send_json_success(array('html'=>$html));
}

add_action('wp_ajax_dgptm_toggle_vote_invalid','dgptm_toggle_vote_invalid_fn');
function dgptm_toggle_vote_invalid_fn(){
    if(!dgptm_is_manager()) wp_send_json_error('Keine Rechte.');
    if(empty($_POST['vote_id'])) wp_send_json_error('vote_id fehlt.');
    global $wpdb; $vid=intval($_POST['vote_id']);
    $row=$wpdb->get_row($wpdb->prepare("SELECT is_invalid FROM {$wpdb->prefix}dgptm_abstimmung_votes WHERE id=%d",$vid));
    if(!$row) wp_send_json_error('Vote nicht gefunden.');
    $new = $row->is_invalid?0:1;
    $res=$wpdb->update($wpdb->prefix.'dgptm_abstimmung_votes',array('is_invalid'=>$new),array('id'=>$vid));
    ($res!==false)?wp_send_json_success():wp_send_json_error('Fehler beim Umschalten.');
}

// ===== BEAMER CONTENT =====
add_action('wp_ajax_dgptm_toggle_beamer_content', 'dgptm_toggle_beamer_content_fn');
function dgptm_toggle_beamer_content_fn() {
    if (!dgptm_is_manager()) wp_send_json_error('Keine Rechte.');
    global $wpdb;
    $poll_id = absint($_POST['poll_id'] ?? 0);
    if (!$poll_id) wp_send_json_error('Keine poll_id.');
    $active = !empty($_POST['active']) ? 1 : 0;
    $wpdb->update(
        $wpdb->prefix . 'dgptm_abstimmung_polls',
        ['beamer_content_active' => $active],
        ['id' => $poll_id]
    );
    wp_send_json_success('Beamer-Inhalt ' . ($active ? 'aktiviert' : 'deaktiviert') . '.');
}

add_action('wp_ajax_dgptm_save_beamer_content', 'dgptm_save_beamer_content_fn');
function dgptm_save_beamer_content_fn() {
    if (!dgptm_is_manager()) wp_send_json_error('Keine Rechte.');
    global $wpdb;
    $poll_id = absint($_POST['poll_id'] ?? 0);
    if (!$poll_id) wp_send_json_error('Keine poll_id.');
    $content = isset($_POST['beamer_content']) ? wp_kses_post($_POST['beamer_content']) : '';
    $wpdb->update(
        $wpdb->prefix . 'dgptm_abstimmung_polls',
        ['beamer_content' => $content],
        ['id' => $poll_id]
    );
    wp_send_json_success('Beamer-Inhalt gespeichert.');
}
