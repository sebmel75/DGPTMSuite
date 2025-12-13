<?php
if (!defined('ABSPATH')) exit;

// ====== SETTINGS MODAL ======
add_action('wp_ajax_dgptm_get_manager_settings','dgptm_get_manager_settings_fn');
function dgptm_get_manager_settings_fn(){
    if(!dgptm_is_manager()) wp_send_json_error('Keine Rechte.');
    $beamer_state = json_decode(get_option('dgptm_beamer_state', wp_json_encode(array('mode'=>'auto'))), true);
    if(!is_array($beamer_state)) $beamer_state = array('mode'=>'auto');
    ob_start(); ?>
    <div class="fieldset" style="border:1px solid #ccc;padding:10px;border-radius:8px;margin-bottom:14px;">
      <h3>Beamer-Einstellungen</h3>
      <form id="dgptm_beamerSettingsForm">
        <label>Text im Beamer (wenn keine Frage aktiv ist):<br>
          <textarea name="dgptm_no_poll_text" rows="2" cols="60"><?php echo esc_html(get_option('dgptm_no_poll_text','Bitte warten …')); ?></textarea>
        </label><br><br>
        <div class="inline" style="display:flex;align-items:center;gap:8px;">
          <label class="switch" title="QR-Code im Beamer ein-/ausblenden (oben rechts)">
            <input type="checkbox" id="beamerQrSwitch" <?php checked(!empty($beamer_state['qr_visible'])); ?>>
            <span class="slider round"></span>
          </label>
          <span>QR-Code im Beamer anzeigen</span>
        </div>
        <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;">
          <button class="btn" id="beamerOverall" type="button">Gesamtstatistik im Beamer</button>
          <button class="btn" id="beamerAuto" type="button">Beamer: Live-Anzeige</button>
          <button type="submit" class="btn">Speichern</button>
        </div>
      </form>
    </div>

    <div class="fieldset" style="border:1px solid #ccc;padding:10px;border-radius:8px;margin-bottom:14px;">
      <h3>Registrierungsfunktion (Kiosk/Scanner)</h3>
      <form id="dgptm_registrationForm">
        <label class="inline" style="display:flex;align-items:center;gap:8px;">
          <input type="checkbox" name="enabled" value="1" <?php checked((int)get_option('dgptm_registration_enabled',0),1); ?>>
          <span>Registrierung aktiv</span>
        </label>
        <div style="margin-top:8px;">
          <label>Ziel-Umfrage (ID): <input type="number" name="poll_id" value="<?php echo (int)get_option('dgptm_registration_poll_id',0); ?>" style="width:120px;"></label>
        </div>
        <div style="margin-top:8px;">
          <label>Lookup-WebHook-URL: <input type="text" name="endpoint" value="<?php echo esc_attr(get_option('dgptm_member_lookup_endpoint','')); ?>" style="width:100%;"></label>
        </div>
        <div style="margin-top:8px;">
          <label>Mail-Betreff: <input type="text" name="mail_subject" value="<?php echo esc_attr(get_option('dgptm_registration_email_subject','Ihr Abstimmungslink')); ?>" style="width:100%;"></label>
        </div>
        <div style="margin-top:8px;">
          <label>Mail-Text (Platzhalter: {first_name} {last_name} {member_no} {status} {poll_name} {link} {token})<br>
            <textarea name="mail_body" rows="5" style="width:100%;"><?php echo esc_textarea(get_option('dgptm_registration_email_body','Hallo {first_name} {last_name},\n\nhier ist Ihr persönlicher Abstimmungslink für {poll_name}:\n{link}\n\nMitgliedsnummer: {member_no}\nStatus: {status}\n\nViele Grüße')); ?></textarea>
          </label>
        </div>
        <div style="margin-top:10px;">
          <button type="submit" class="btn">Einstellungen speichern</button>
          <button type="button" class="btn" id="dgptm_showRegistered">Registrierte Mitglieder anzeigen</button>
        </div>
      </form>
      <div id="dgptm_registeredList" style="margin-top:10px;"></div>
    </div>
    <?php
    $html = ob_get_clean();
    wp_send_json_success(array('html'=>$html));
}

add_action('wp_ajax_dgptm_save_beamer_settings','dgptm_save_beamer_settings_fn');
function dgptm_save_beamer_settings_fn(){
    if(!dgptm_is_manager()) wp_send_json_error('Keine Rechte.');
    update_option('dgptm_no_poll_text', isset($_POST['dgptm_no_poll_text'])?sanitize_text_field($_POST['dgptm_no_poll_text']):'');
    wp_send_json_success();
}

add_action('wp_ajax_dgptm_toggle_beamer_qr','dgptm_toggle_beamer_qr_fn');
function dgptm_toggle_beamer_qr_fn(){
    if(!dgptm_is_manager()) wp_send_json_error('Keine Rechte.');
    $visible = !empty($_POST['visible']) ? 1 : 0;
    $state = json_decode(get_option('dgptm_beamer_state', wp_json_encode(array('mode'=>'auto'))), true);
    if(!is_array($state)) $state=array('mode'=>'auto');
    $state['qr_visible'] = $visible ? true : false;
    update_option('dgptm_beamer_state', wp_json_encode($state));
    wp_send_json_success();
}

add_action('wp_ajax_dgptm_set_beamer_state','dgptm_set_beamer_state_fn');
function dgptm_set_beamer_state_fn(){
    if(!dgptm_is_manager()) wp_send_json_error('Keine Rechte.');
    $mode = sanitize_text_field(isset($_POST['mode'])?$_POST['mode']:'auto');
    $st = json_decode(get_option('dgptm_beamer_state', wp_json_encode(array('mode'=>'auto'))), true);
    if(!is_array($st)) $st=array('mode'=>'auto');
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
    $state = json_decode(get_option('dgptm_beamer_state', wp_json_encode(array('mode'=>'auto'))), true);
    if(!is_array($state)) $state=array('mode'=>'auto');
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

    ob_start(); ?>
    <p class="muted">
      <strong>Benutzeranmeldung:</strong> <?php echo $poll->requires_signup?'Ja':'Nein'; ?><br>
      <strong>Logo (nur aktives Poll-Logo wird im Beamer genutzt):</strong>
      <?php if(!empty($poll->logo_url)){
        if(dgptm_is_image_ext($poll->logo_url)){
          echo '<div style="margin-top:5px;"><img src="'.esc_url($poll->logo_url).'" alt="Poll-Logo" style="max-width:200px;"><br><a href="'.esc_url($poll->logo_url).'" download>Bild herunterladen</a></div>';
        } else { echo esc_html($poll->logo_url); }
      } else { echo 'Kein Logo definiert.'; } ?>
    </p>

    <div style="margin:12px 0;">
      <h4>Teilnahme per QR-Code</h4>
      <div class="muted" style="margin-bottom:6px;">Externe Teilnahme (Namensabfrage für Nicht-Eingeloggte). Sichtbarkeit im Beamer über „Einstellungen → Beamer-Einstellungen“ steuerbar.</div>
      <?php $qrUrl = add_query_arg(array('dgptm_member'=>1,'poll_id'=>$poll->id), home_url('/')); ?>
      <div><strong>URL:</strong> <code id="qrUrlText_<?php echo (int)$poll->id; ?>" data-qr="<?php echo esc_url($qrUrl); ?>"><?php echo esc_html($qrUrl); ?></code></div>
      <div style="margin-top:6px;">
        <button class="btn" data-pid="<?php echo (int)$poll->id; ?>" onclick="dgptmDownloadQR(<?php echo (int)$poll->id; ?>)">QR als PNG herunterladen</button>
      </div>
      <canvas id="qrCanvas_<?php echo (int)$poll->id; ?>" class="qrcode-canvas" width="260" height="260"></canvas>
    </div>

    <hr>
    <h4>Fragen</h4>
    <?php
      $questions = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_poll_questions WHERE poll_id=%d ORDER BY created ASC",$poll->id));
      if($questions):
        foreach($questions as $q):
          $choices = json_decode($q->choices,true); if(!is_array($choices)) $choices=array();
          $choicesStr = implode(', ', $choices);
          $is_results_one = false; // wird per Klick gesetzt
    ?>
      <div style="border:1px solid #ccc;margin:5px;padding:5px;border-radius:6px;">
        <strong>Frage:</strong> <?php echo esc_html($q->question); ?>
        <?php if($q->status==='active') echo '<span class="muted" style="margin-left:8px;">(AKTIV)</span>'; ?>
        <div class="muted">Status: <?php echo esc_html($q->status); ?>,
        max. Stimmen: <?php echo (int)$q->max_votes; ?>,
        Ergebnis freigegeben:
        <label class="switch" style="margin-left:6px;">
          <input type="checkbox" class="resultReleaseSwitch" data-qid="<?php echo (int)$q->id; ?>" <?php checked($q->results_released); ?>>
          <span class="slider round"></span>
        </label>
        &nbsp; | &nbsp; In Gesamtstatistik:
        <label class="switch" title="Diese Frage in der Gesamtstatistik berücksichtigen">
          <input type="checkbox" class="overallSwitch" data-qid="<?php echo (int)$q->id; ?>" <?php checked(!empty($q->in_overall)); ?>>
          <span class="slider round"></span>
        </label>
        </div>

        <form class="inline updateQuestionForm" data-qid="<?php echo (int)$q->id; ?>" style="margin-top:6px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
          <input type="hidden" name="question_text" value="<?php echo esc_attr($q->question); ?>">
          <input type="hidden" name="question_choices" value="<?php echo esc_attr($choicesStr); ?>">
          <input type="hidden" name="max_votes" value="<?php echo (int)$q->max_votes; ?>">
          <input type="hidden" name="is_anonymous" value="<?php echo $q->is_anonymous ? 1 : 0; ?>">
          <label>Diagramm:
            <select name="chart_type">
              <option value="bar" <?php selected($q->chart_type,'bar'); ?>>Balken</option>
              <option value="pie" <?php selected($q->chart_type,'pie'); ?>>Kuchen</option>
            </select>
          </label>
          <button type="submit" class="btn">Speichern</button>
        </form>

        <div class="row-actions" style="margin:6px 0;">
          <?php if($q->status=='prepared'): ?>
            <button class="questionCtrlBtn btn" data-action="dgptm_activate_poll_question" data-qid="<?php echo (int)$q->id; ?>">Aktivieren</button>
          <?php elseif($q->status=='active'): ?>
            <button class="questionCtrlBtn btn" data-action="dgptm_stop_poll_question" data-qid="<?php echo (int)$q->id; ?>">Schließen</button>
          <?php elseif($q->status=='stopped'): ?>
            <button class="questionCtrlBtn btn" data-action="dgptm_prepare_poll_question" data-qid="<?php echo (int)$q->id; ?>">Erneut stellen</button>
          <?php endif; ?>
          <label class="switch" title="Statistik dieser Frage im Beamer anzeigen/verbergen" style="margin-left:12px;">
            <input type="checkbox" class="beamerDisplaySwitch" data-qid="<?php echo (int)$q->id; ?>" data-pid="<?php echo (int)$poll->id; ?>" <?php checked($is_results_one); ?>>
            <span class="slider round"></span>
          </label>
          <button class="deleteQuestionBtn btn" data-qid="<?php echo (int)$q->id; ?>">Frage löschen</button>
          <button class="showVotesBtn btn" data-qid="<?php echo (int)$q->id; ?>">Stimmen anzeigen</button>
        </div>

        <div id="votesArea_<?php echo (int)$q->id; ?>" style="display:none;"></div>
      </div>
    <?php endforeach; else: ?>
      <p>Noch keine Fragen</p>
    <?php endif; ?>

    <hr>
    <h4>Neue Frage anlegen</h4>
    <form class="addQuestionForm">
      <input type="hidden" name="poll_id" value="<?php echo (int)$poll->id; ?>">
      <label>Fragetext:<br><input type="text" name="question_text" required></label><br>
      <label>Antworten (Komma-getrennt):<br><textarea name="question_choices"></textarea></label><br>
      <label>max. Stimmen: <input type="number" name="max_votes" value="1"></label><br>
      <label>Anonym? <input type="checkbox" name="is_anonymous"></label><br>
      <label>Diagramm:
        <select name="chart_type">
          <option value="bar" selected>Balken</option>
          <option value="pie">Kuchen</option>
        </select>
      </label><br>
      <button type="submit" class="btn">Frage anlegen</button>
    </form>

    <hr>
    <button class="showParticipantsBtn btn" data-pid="<?php echo (int)$poll->id; ?>">Teilnehmer anzeigen</button>
    <div id="participantsArea_<?php echo (int)$poll->id; ?>" style="display:none;"></div>

    <hr>
    <div>
      <a class="btn" href="<?php echo esc_url(admin_url('admin-ajax.php?action=dgptm_export_poll_complete&poll_id='.(int)$poll->id.'&format=csv')); ?>" target="_blank">Als CSV exportieren</a>
      <a class="btn" href="<?php echo esc_url(admin_url('admin-ajax.php?action=dgptm_export_poll_complete&poll_id='.(int)$poll->id.'&format=pdf')); ?>" target="_blank">Als PDF exportieren</a>
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
    $wpdb->insert($wpdb->prefix.'dgptm_abstimmung_polls',array(
        'name'=>sanitize_text_field($_POST['poll_name']),
        'status'=>'prepared',
        'created'=>current_time('mysql'),
        'requires_signup'=>!empty($_POST['requires_signup'])?1:0,
        'time_limit'=>0,
        'logo_url'=>isset($_POST['poll_logo_url'])?sanitize_text_field($_POST['poll_logo_url']):''
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
    $state = json_decode(get_option('dgptm_beamer_state', wp_json_encode(array('mode'=>'auto'))), true);
    if(is_array($state) && isset($state['mode']) && $state['mode']==='results_all' && !empty($state['poll_id']) && (int)$state['poll_id']===$pid){
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
    $chart_type = (isset($_POST['chart_type']) && in_array($_POST['chart_type'],array('bar','pie'),true)) ? $_POST['chart_type'] : 'bar';
    $res = $wpdb->insert($wpdb->prefix.'dgptm_abstimmung_poll_questions',array(
        'poll_id'=>$pid,
        'question'=>sanitize_text_field($_POST['question_text']),
        'choices'=>wp_json_encode($choices),
        'max_votes'=>isset($_POST['max_votes'])?intval($_POST['max_votes']):1,
        'status'=>'prepared',
        'created'=>current_time('mysql'),
        'time_limit'=>0,
        'max_choices'=>0,
        'is_repeatable'=>1,
        'is_anonymous'=>!empty($_POST['is_anonymous'])?1:0,
        'chart_type'=>$chart_type,
        'in_overall'=>0
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
    $chart_type = (isset($_POST['chart_type']) && in_array($_POST['chart_type'],array('bar','pie'),true)) ? $_POST['chart_type'] : 'bar';
    $res=$wpdb->update($wpdb->prefix.'dgptm_abstimmung_poll_questions',array(
        'question'=>sanitize_text_field($_POST['question_text']),
        'choices'=>wp_json_encode($choices),
        'max_votes'=>isset($_POST['max_votes'])?intval($_POST['max_votes']):1,
        'is_anonymous'=>!empty($_POST['is_anonymous'])?1:0,
        'chart_type'=>$chart_type
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
    $res=$wpdb->update($wpdb->prefix.'dgptm_abstimmung_poll_questions',array('status'=>'active','created'=>current_time('mysql'),'ended'=>null),array('id'=>$qid));
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
