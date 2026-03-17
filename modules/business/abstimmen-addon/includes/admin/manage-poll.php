<?php
// File: includes/admin/manage-poll.php
// Manager-UI v4.3 — kompakt, filigran, Beamer-Live-Steuerung integriert

if (!defined('ABSPATH')) exit;

if (!function_exists('dgptm_manage_poll')) {
    function dgptm_manage_poll() {
        wp_enqueue_style( 'dgptm-abstimmen-frontend' );
        wp_enqueue_script( 'dgptm-abstimmen-frontend' );

        if (!function_exists('dgptm_is_manager') || !dgptm_is_manager()) {
            return '<p>Keine Berechtigung.</p>';
        }

        global $wpdb;
        $polls = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_polls ORDER BY created DESC");
        $beamer_state = dgptm_get_beamer_state();
        $no_poll_text = get_option('dgptm_no_poll_text', 'Bitte warten …');
        $reg_enabled  = (int)get_option('dgptm_registration_enabled', 0);
        $reg_poll_id  = (int)get_option('dgptm_registration_poll_id', 0);
        $reg_endpoint = get_option('dgptm_member_lookup_endpoint', '');
        $reg_subject  = get_option('dgptm_registration_email_subject', 'Ihr Abstimmungslink');
        $reg_body     = get_option('dgptm_registration_email_body', "Hallo {first_name} {last_name},\n\nhier ist Ihr persönlicher Abstimmungslink für {poll_name}:\n{link}\n\nMitgliedsnummer: {member_no}\nStatus: {status}\n\nViele Grüße");

        ob_start(); ?>
        <style>
          :root{--mp-blue:#2563eb;--mp-blue-h:#1d4ed8;--mp-green:#16a34a;--mp-red:#dc2626;--mp-gray:#64748b;--mp-border:#e2e8f0;--mp-bg:#f8fafc;--mp-radius:6px;}
          .mp *{box-sizing:border-box;font-family:system-ui,-apple-system,sans-serif;}
          .mp{font-size:13px;color:#1e293b;max-width:1100px;}
          .mp h2{font-size:18px;margin:0 0 10px;font-weight:700;}
          .mp h3{font-size:14px;margin:8px 0 6px;font-weight:600;}
          .mp h4{font-size:13px;margin:10px 0 4px;font-weight:600;}
          .mp hr{border:none;border-top:1px solid var(--mp-border);margin:10px 0;}
          /* Toolbar */
          .mp-toolbar{display:flex;gap:6px;flex-wrap:wrap;align-items:center;padding:8px 10px;background:var(--mp-bg);border:1px solid var(--mp-border);border-radius:var(--mp-radius);margin-bottom:10px;}
          .mp-toolbar .mp-sep{width:1px;height:22px;background:var(--mp-border);}
          /* Buttons */
          .mp-btn{display:inline-flex;align-items:center;gap:4px;border:1px solid var(--mp-border);background:#fff;color:#334155;padding:4px 10px;border-radius:var(--mp-radius);font-size:12px;font-weight:500;cursor:pointer;transition:all .12s;line-height:1.4;}
          .mp-btn:hover{border-color:#94a3b8;background:#f1f5f9;}
          .mp-btn-p{background:var(--mp-blue);color:#fff;border-color:var(--mp-blue);}
          .mp-btn-p:hover{background:var(--mp-blue-h);}
          .mp-btn-s{background:#f1f5f9;color:#475569;}
          .mp-btn-d{background:var(--mp-red);color:#fff;border-color:var(--mp-red);font-size:11px;padding:2px 8px;}
          .mp-btn-d:hover{background:#b91c1c;}
          .mp-btn-g{background:var(--mp-green);color:#fff;border-color:var(--mp-green);}
          .mp-btn-g:hover{background:#15803d;}
          .mp-btn[disabled]{opacity:.45;pointer-events:none;}
          .mp-btn-xs{font-size:11px;padding:2px 6px;}
          /* Toggle switch — slim */
          .mp-sw{position:relative;display:inline-block;width:32px;height:16px;vertical-align:middle;flex-shrink:0;}
          .mp-sw input{display:none;}
          .mp-sw span{position:absolute;cursor:pointer;inset:0;background:#cbd5e1;border-radius:16px;transition:.2s;}
          .mp-sw span:before{content:"";position:absolute;height:12px;width:12px;left:2px;bottom:2px;background:#fff;border-radius:50%;transition:.2s;box-shadow:0 1px 2px rgba(0,0,0,.12);}
          .mp-sw input:checked+span{background:var(--mp-green);}
          .mp-sw input:checked+span:before{transform:translateX(16px);}
          /* Badge */
          .mp-badge{display:inline-block;font-size:10px;font-weight:600;padding:1px 6px;border-radius:10px;text-transform:uppercase;letter-spacing:.03em;}
          .mp-badge-active{background:#dcfce7;color:#166534;}
          .mp-badge-prepared{background:#fef3c7;color:#92400e;}
          .mp-badge-archived{background:#f1f5f9;color:#64748b;}
          .mp-badge-stopped{background:#fee2e2;color:#991b1b;}
          /* Table */
          .mp-tbl{width:100%;border-collapse:collapse;font-size:12px;}
          .mp-tbl th,.mp-tbl td{border:1px solid var(--mp-border);padding:5px 8px;text-align:left;}
          .mp-tbl th{background:var(--mp-bg);font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:var(--mp-gray);font-weight:600;}
          .mp-tbl tr:hover td{background:#fafbfd;}
          /* Panel */
          .mp-panel{border:1px solid var(--mp-border);border-radius:var(--mp-radius);margin:8px 0;overflow:hidden;}
          .mp-panel-head{display:flex;align-items:center;gap:6px;padding:6px 10px;background:var(--mp-bg);cursor:pointer;font-size:12px;font-weight:600;user-select:none;}
          .mp-panel-head:hover{background:#eef2f7;}
          .mp-panel-head .arrow{transition:transform .2s;font-size:10px;color:var(--mp-gray);}
          .mp-panel-head.open .arrow{transform:rotate(90deg);}
          .mp-panel-body{padding:8px 10px;display:none;font-size:12px;}
          .mp-panel-body.open{display:block;}
          /* Inline form */
          .mp-row{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:4px 0;}
          .mp-row label{font-size:12px;color:#475569;}
          .mp-row input[type=text],.mp-row input[type=number],.mp-row textarea,.mp-row select{font-size:12px;padding:3px 6px;border:1px solid var(--mp-border);border-radius:4px;font-family:inherit;}
          .mp-row textarea{resize:vertical;}
          /* Question card */
          .mp-qcard{border:1px solid var(--mp-border);border-radius:var(--mp-radius);padding:8px 10px;margin:6px 0;font-size:12px;background:#fff;transition:border-color .15s;}
          .mp-qcard:hover{border-color:#94a3b8;}
          .mp-qcard-head{display:flex;justify-content:space-between;align-items:flex-start;gap:8px;}
          .mp-qcard-q{font-weight:600;font-size:13px;flex:1;}
          .mp-qcard-meta{color:var(--mp-gray);font-size:11px;margin-top:3px;}
          .mp-qcard-actions{display:flex;gap:4px;flex-wrap:wrap;align-items:center;margin-top:6px;}
          /* QR section */
          .mp-qr{display:none;margin:6px 0;padding:8px;border:1px dashed var(--mp-border);border-radius:var(--mp-radius);background:#fff;text-align:center;}
          .mp-qr.open{display:block;}
          .mp-qr canvas{width:180px!important;height:180px!important;}
          /* Beamer control bar */
          .mp-beamer-bar{display:flex;gap:6px;align-items:center;padding:6px 10px;background:linear-gradient(90deg,#0f172a,#1e293b);border-radius:var(--mp-radius);margin-bottom:10px;color:#e2e8f0;font-size:12px;}
          .mp-beamer-bar .mp-beamer-label{font-weight:600;color:#94a3b8;text-transform:uppercase;font-size:10px;letter-spacing:.06em;}
          .mp-beamer-bar .mp-beamer-status{display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;}
          .mp-beamer-bar .status-live{background:#16a34a;color:#fff;}
          .mp-beamer-bar .status-result{background:#f59e0b;color:#000;}
          .mp-beamer-bar .status-custom{background:#8b5cf6;color:#fff;}
          .mp-beamer-bar .mp-btn{font-size:11px;padding:3px 8px;}
          /* Detail area */
          .mp-detail{padding:10px;border-top:1px solid var(--mp-border);background:#fafbfd;}
          /* Responsive */
          @media(max-width:768px){
            .mp-toolbar,.mp-beamer-bar{flex-direction:column;align-items:stretch;}
            .mp-toolbar .mp-sep{display:none;}
            .mp-qcard-head{flex-direction:column;}
          }
        </style>

        <script>window.dgptm_ajax=window.dgptm_ajax||{ajax_url:"<?php echo esc_js(admin_url('admin-ajax.php')); ?>"};</script>
        <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>

        <div class="mp" id="dgptm_managePoll">
          <h2>Abstimmungs-Manager</h2>

          <!-- Beamer Live-Steuerung -->
          <div class="mp-beamer-bar">
            <span class="mp-beamer-label">Beamer</span>
            <?php
              $bm = $beamer_state['mode'] ?? 'auto';
              $statusClass = 'status-live';
              $statusLabel = 'Live';
              if ($bm === 'results_all') { $statusClass = 'status-result'; $statusLabel = 'Alle Ergebnisse'; }
              elseif ($bm === 'results_one') { $statusClass = 'status-result'; $statusLabel = 'Einzelergebnis'; }
            ?>
            <span class="mp-beamer-status <?php echo $statusClass; ?>" id="beamerStatusBadge"><?php echo $statusLabel; ?></span>
            <button class="mp-btn" id="beamerAuto" type="button">Live</button>
            <button class="mp-btn" id="beamerOverall" type="button">Gesamt</button>
            <button class="mp-btn" id="beamerPause" type="button" style="background:#8b5cf6;color:#fff;border-color:#8b5cf6;">⏸ Pause</button>
            <span class="mp-sep" style="background:#475569;"></span>
            <label style="display:flex;align-items:center;gap:4px;cursor:pointer;">
              <label class="mp-sw"><input type="checkbox" id="beamerQrSwitch" <?php checked(!empty($beamer_state['qr_visible'])); ?>><span></span></label>
              QR
            </label>
            <label style="display:flex;align-items:center;gap:4px;cursor:pointer;">
              <label class="mp-sw"><input type="checkbox" id="beamerContentGlobal" <?php
                // Check if any active poll has beamer_content_active
                $any_content_active = false;
                if ($polls) { foreach ($polls as $p) { if ($p->status === 'active' && !empty($p->beamer_content_active)) $any_content_active = true; } }
                checked($any_content_active);
              ?>><span></span></label>
              Pause-Text
            </label>
          </div>

          <!-- Toolbar -->
          <div class="mp-toolbar">
            <button class="mp-btn mp-btn-p" id="newPollBtn">+ Neue Umfrage</button>
            <span class="mp-sep"></span>
            <button class="mp-btn mp-btn-s" id="toggleSettings">Einstellungen</button>
          </div>

          <!-- Einstellungen inline (collapsed) -->
          <div id="settingsPanel" style="display:none;">
            <div class="mp-panel">
              <div class="mp-panel-head open" onclick="this.classList.toggle('open');this.nextElementSibling.classList.toggle('open');">
                <span class="arrow">▶</span> Beamer-Einstellungen
              </div>
              <div class="mp-panel-body open">
                <form id="dgptm_beamerSettingsForm">
                  <div class="mp-row"><label>Pause-Text (wird angezeigt wenn keine Frage aktiv und kein Umfrage-Pauseninhalt gesetzt ist):</label></div>
                  <div class="mp-wysiwyg-toolbar" style="display:flex;gap:2px;flex-wrap:wrap;padding:3px;background:var(--mp-bg);border:1px solid var(--mp-border);border-bottom:none;border-radius:4px 4px 0 0;">
                    <button type="button" class="mp-btn mp-btn-xs mp-wysiwyg-cmd" data-cmd="bold"><b>B</b></button>
                    <button type="button" class="mp-btn mp-btn-xs mp-wysiwyg-cmd" data-cmd="italic"><i>I</i></button>
                    <button type="button" class="mp-btn mp-btn-xs mp-wysiwyg-cmd" data-cmd="underline"><u>U</u></button>
                    <button type="button" class="mp-btn mp-btn-xs mp-wysiwyg-cmd" data-cmd="justifyCenter">⎍</button>
                    <button type="button" class="mp-btn mp-btn-xs mp-wysiwyg-cmd" data-cmd="fontSize" data-val="5">A+</button>
                    <button type="button" class="mp-btn mp-btn-xs mp-wysiwyg-img-global" title="Bild">🖼</button>
                    <button type="button" class="mp-btn mp-btn-xs mp-wysiwyg-src-global" title="HTML">&lt;/&gt;</button>
                  </div>
                  <div id="settingsWysiwyg" class="mp-wysiwyg-editor" contenteditable="true" style="min-height:50px;max-height:180px;overflow:auto;padding:8px;border:1px solid var(--mp-border);border-radius:0 0 4px 4px;font-size:13px;background:#fff;outline:none;"><?php echo $no_poll_text; ?></div>
                  <textarea name="dgptm_no_poll_text" id="settingsNoPollText" style="display:none;"><?php echo esc_textarea($no_poll_text); ?></textarea>
                  <div class="mp-row">
                    <button type="submit" class="mp-btn mp-btn-p">Speichern</button>
                  </div>
                </form>
              </div>
            </div>
            <div class="mp-panel">
              <div class="mp-panel-head" onclick="this.classList.toggle('open');this.nextElementSibling.classList.toggle('open');">
                <span class="arrow">▶</span> Registrierung (Kiosk/Scanner)
              </div>
              <div class="mp-panel-body">
                <form id="dgptm_registrationForm">
                  <div class="mp-row">
                    <label class="mp-sw"><input type="checkbox" name="enabled" value="1" <?php checked($reg_enabled, 1); ?>><span></span></label>
                    <label>Registrierung aktiv</label>
                  </div>
                  <div class="mp-row"><label>Ziel-Umfrage (ID):</label><input type="number" name="poll_id" value="<?php echo $reg_poll_id; ?>" style="width:80px;"></div>
                  <div class="mp-row"><label>Lookup-Webhook:</label><input type="text" name="endpoint" value="<?php echo esc_attr($reg_endpoint); ?>" style="width:100%;"></div>
                  <div class="mp-row"><label>Mail-Betreff:</label><input type="text" name="mail_subject" value="<?php echo esc_attr($reg_subject); ?>" style="width:100%;"></div>
                  <div class="mp-row">
                    <label>Mail-Text ({first_name} {last_name} {member_no} {status} {poll_name} {link} {token}):</label>
                  </div>
                  <div class="mp-row"><textarea name="mail_body" rows="4" style="width:100%;"><?php echo esc_textarea($reg_body); ?></textarea></div>
                  <div class="mp-row">
                    <button type="submit" class="mp-btn mp-btn-p">Speichern</button>
                    <button type="button" class="mp-btn" id="dgptm_showRegistered">Registrierte anzeigen</button>
                  </div>
                </form>
                <div id="dgptm_registeredList" style="margin-top:6px;"></div>
              </div>
            </div>
          </div>

          <!-- Neue Umfrage (collapsed) -->
          <div id="newPollForm" style="display:none;margin:8px 0;">
            <div class="mp-panel">
              <div class="mp-panel-head open"><span class="arrow">▶</span> Neue Umfrage anlegen</div>
              <div class="mp-panel-body open">
                <form id="createPollForm">
                  <div class="mp-row"><label>Name:</label><input type="text" name="poll_name" required style="flex:1;min-width:200px;"></div>
                  <div class="mp-row">
                    <label class="mp-sw"><input type="checkbox" name="requires_signup"><span></span></label><label>Anmeldepflicht</label>
                    <label class="mp-sw" style="margin-left:12px;"><input type="checkbox" name="guest_voting" checked><span></span></label><label>Gäste erlauben</label>
                  </div>
                  <div class="mp-row"><label>Logo-URL:</label><input type="text" name="poll_logo_url" placeholder="https://..." style="flex:1;"></div>
                  <div class="mp-row"><label>Beamer-Pause-Inhalt (HTML):</label></div>
                  <div class="mp-row"><textarea name="beamer_content" rows="2" style="width:100%;" placeholder="QR-Code HTML, Willkommenstext..."></textarea></div>
                  <div class="mp-row"><button type="submit" class="mp-btn mp-btn-p">Anlegen</button></div>
                </form>
              </div>
            </div>
          </div>

          <!-- Umfragen-Tabelle -->
          <table class="mp-tbl">
            <thead><tr>
              <th style="width:36px;">ID</th>
              <th>Name</th>
              <th style="width:70px;">Status</th>
              <th style="width:50px;">Aktiv</th>
              <th style="width:60px;">Beamer</th>
              <th style="width:50px;"></th>
            </tr></thead>
            <tbody>
            <?php if ($polls): foreach ($polls as $poll):
              $is_active = ($poll->status === 'active');
              $all_on = (is_array($beamer_state) && ($beamer_state['mode'] ?? '') === 'results_all' && !empty($beamer_state['poll_id']) && (int)$beamer_state['poll_id'] === (int)$poll->id);
            ?>
              <tr class="poll-header" data-poll-id="<?php echo (int)$poll->id; ?>" data-active="<?php echo $is_active ? 1 : 0; ?>">
                <td><?php echo (int)$poll->id; ?></td>
                <td><strong><?php echo esc_html($poll->name); ?></strong></td>
                <td><span class="mp-badge mp-badge-<?php echo esc_attr($poll->status); ?>"><?php echo esc_html($poll->status); ?></span></td>
                <td>
                  <label class="mp-sw"><input type="checkbox" class="pollToggleSwitch" data-id="<?php echo (int)$poll->id; ?>" <?php checked($is_active); ?>><span></span></label>
                </td>
                <td>
                  <label class="mp-sw" title="Alle freigegebenen Ergebnisse im Beamer">
                    <input type="checkbox" class="beamerAllSwitch" data-pid="<?php echo (int)$poll->id; ?>" <?php checked($all_on); ?>>
                    <span></span>
                  </label>
                </td>
                <td><button class="mp-btn mp-btn-xs loadDetailsBtn" data-id="<?php echo (int)$poll->id; ?>">Details</button></td>
              </tr>
              <tr class="poll-details" style="display:none;">
                <td colspan="6"><div id="pollDetails_<?php echo (int)$poll->id; ?>" data-loaded="0"></div></td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="6" style="color:var(--mp-gray);">Keine Umfragen vorhanden</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>

        <script>
        (function waitForDeps(){
          if(typeof jQuery==='undefined'||!window.dgptm_ajax)return setTimeout(waitForDeps,50);
          jQuery(function($){

            function enc(s){try{return encodeURIComponent(s)}catch(e){return s}}

            // QR zeichnen — immer via qrserver.com API (zuverlaessig schwarz auf weiss)
            window.dgptmDrawQR=function(pid){
              var container=document.getElementById('qrCanvas_'+pid);if(!container)return;
              var urlNode=document.getElementById('qrUrlText_'+pid);if(!urlNode)return;
              var url=urlNode.getAttribute('data-qr')||urlNode.textContent||'';
              var imgSrc='https://api.qrserver.com/v1/create-qr-code/?size=360x360&color=000000&bgcolor=ffffff&data='+enc(url);
              var img=new Image();img.alt='QR';img.id='qrCanvas_'+pid;
              img.style.cssText='width:180px;height:180px;display:block;border-radius:4px;';
              img.src=imgSrc;
              container.replaceWith(img);
            };
            window.dgptmDownloadQR=function(pid){
              var c=document.getElementById('qrCanvas_'+pid);
              if(!c){dgptmDrawQR(pid);c=document.getElementById('qrCanvas_'+pid);}
              if(!c)return;
              var a=document.createElement('a');
              if(c.tagName&&c.tagName.toLowerCase()==='img'){a.href=c.src;}
              else{a.href=c.toDataURL('image/png');}
              a.download='qr_poll_'+pid+'.png';a.click();
            };

            // Toolbar toggles
            $('#newPollBtn').on('click',function(e){e.preventDefault();$('#newPollForm').slideToggle(150);});
            $('#toggleSettings').on('click',function(e){e.preventDefault();$('#settingsPanel').slideToggle(150);});

            // Create poll
            $('#createPollForm').on('submit',function(e){
              e.preventDefault();
              $.post(dgptm_ajax.ajax_url,$(this).serialize()+'&action=dgptm_create_poll',function(r){
                if(r&&r.success){location.reload();}
                else{alert('Fehler: '+(r?r.data:''));}
              },'json');
            });

            // Poll toggle
            $(document).on('change','.pollToggleSwitch',function(){
              var pid=$(this).data('id');
              $.post(dgptm_ajax.ajax_url,{action:'dgptm_toggle_poll_status',poll_id:pid},function(r){
                if(r&&r.success)location.reload();
                else alert('Fehler: '+(r?r.data:''));
              },'json');
            });

            // Beamer: Alle Ergebnisse
            $(document).on('change','.beamerAllSwitch',function(){
              var pid=$(this).data('pid'),chk=$(this).is(':checked');
              $.post(dgptm_ajax.ajax_url,{action:'dgptm_toggle_beamer_all_for_poll',poll_id:pid},function(r){
                if(r&&r.success)updateBeamerBadge(r.data.state==='on'?'Alle Ergebnisse':'Live');
                else{alert('Fehler: '+(r?r.data:''));$('.beamerAllSwitch[data-pid="'+pid+'"]').prop('checked',!chk);}
              },'json');
            });

            function updateBeamerBadge(label){
              var $b=$('#beamerStatusBadge');
              $b.text(label);
              $b.removeClass('status-live status-result status-custom');
              if(label==='Live')$b.addClass('status-live');
              else if(label.indexOf('Ergebnis')>-1)$b.addClass('status-result');
              else $b.addClass('status-custom');
            }

            // Beamer control bar
            $('#beamerAuto').on('click',function(){
              $.post(dgptm_ajax.ajax_url,{action:'dgptm_set_beamer_state',mode:'auto'},function(r){
                if(r&&r.success)updateBeamerBadge('Live');
                else alert('Fehler: '+(r?r.data:''));
              },'json');
            });
            // Beamer: Pause (reset to auto + activate beamer_content)
            $('#beamerPause').on('click',function(){
              // First reset to auto mode
              $.post(dgptm_ajax.ajax_url,{action:'dgptm_set_beamer_state',mode:'auto'},function(r){
                if(r&&r.success){
                  // Then activate beamer_content on active poll
                  var $activeRow=$('.poll-header[data-active="1"]').first();
                  if($activeRow.length){
                    var pid=$activeRow.data('poll-id');
                    $.post(dgptm_ajax.ajax_url,{action:'dgptm_toggle_beamer_content',poll_id:pid,active:1},function(r2){
                      updateBeamerBadge('Pause-Text');
                      $('#beamerContentGlobal').prop('checked',true);
                    },'json');
                  }else{updateBeamerBadge('Live');}
                }else alert('Fehler: '+(r?r.data:''));
              },'json');
            });
            $('#beamerOverall').on('click',function(){
              $.post(dgptm_ajax.ajax_url,{action:'dgptm_set_beamer_state_overall'},function(r){
                if(r&&r.success)updateBeamerBadge('Alle Ergebnisse');
                else alert('Fehler: '+(r?r.data:''));
              },'json');
            });
            $('#beamerQrSwitch').on('change',function(){
              var chk=$(this).is(':checked');
              $.post(dgptm_ajax.ajax_url,{action:'dgptm_toggle_beamer_qr',visible:chk?1:0},function(r){
                if(!(r&&r.success)){alert('Fehler');$('#beamerQrSwitch').prop('checked',!chk);}
              },'json');
            });
            // Beamer: Pause-Text global toggle (active poll)
            $('#beamerContentGlobal').on('change',function(){
              var chk=$(this).is(':checked');
              // Find active poll
              var $activeRow=$('.poll-header[data-active="1"]').first();
              if(!$activeRow.length){alert('Keine aktive Umfrage.');$(this).prop('checked',!chk);return;}
              var pid=$activeRow.data('poll-id');
              $.post(dgptm_ajax.ajax_url,{action:'dgptm_toggle_beamer_content',poll_id:pid,active:chk?1:0},function(r){
                if(r&&r.success)updateBeamerBadge(chk?'Pause-Text':'Live');
                else{alert('Fehler: '+(r?r.data:''));$('#beamerContentGlobal').prop('checked',!chk);}
              },'json');
            });

            // Settings WYSIWYG handlers
            $(document).on('click','.mp-wysiwyg-img-global',function(e){
              e.preventDefault();var url=prompt('Bild-URL:');if(url)document.execCommand('insertImage',false,url);
            });
            $(document).on('click','.mp-wysiwyg-src-global',function(e){
              e.preventDefault();
              var $ed=$('#settingsWysiwyg'),$ta=$('#settingsNoPollText');
              if($ta.is(':visible')){$ed.html($ta.val()).show();$ta.hide();$(this).text('</>')}
              else{$ta.val($ed.html()).show();$ed.hide();$(this).text('Vorschau');}
            });
            // Settings forms
            $(document).on('submit','#dgptm_beamerSettingsForm',function(e){
              e.preventDefault();
              // Sync WYSIWYG to textarea
              var $ed=$('#settingsWysiwyg'),$ta=$('#settingsNoPollText');
              if($ed.is(':visible'))$ta.val($ed.html());
              $.post(dgptm_ajax.ajax_url,{action:'dgptm_save_beamer_settings',dgptm_no_poll_text:$ta.val()},function(r){
                alert(r&&r.success?'Gespeichert.':'Fehler: '+(r?r.data:''));
              },'json');
            });
            $(document).on('submit','#dgptm_registrationForm',function(e){
              e.preventDefault();
              var $f=$(this);
              $.post(dgptm_ajax.ajax_url,{
                action:'dgptm_save_registration_settings',
                enabled:$f.find('input[name="enabled"]').is(':checked')?1:0,
                poll_id:$f.find('input[name="poll_id"]').val(),
                endpoint:$f.find('input[name="endpoint"]').val(),
                mail_subject:$f.find('input[name="mail_subject"]').val(),
                mail_body:$f.find('textarea[name="mail_body"]').val()
              },function(r){alert(r&&r.success?'Gespeichert.':'Fehler: '+(r?r.data:''));},'json');
            });
            $(document).on('click','#dgptm_showRegistered',function(e){
              e.preventDefault();
              $.post(dgptm_ajax.ajax_url,{action:'dgptm_get_registered_members'},function(r){
                if(r&&r.success)$('#dgptm_registeredList').html(r.data.html);
                else alert('Fehler');
              },'json');
            });

            // Details lazy-load
            function loadDetails(pid,$row,$box){
              if(!$row||!$row.length)$row=$('.poll-header[data-poll-id="'+pid+'"]').next('.poll-details');
              if(!$box||!$box.length)$box=$('#pollDetails_'+pid);
              if($box.data('loaded')!=1){
                $box.html('<div style="padding:8px;color:var(--mp-gray);">Lade …</div>');
                $.post(dgptm_ajax.ajax_url,{action:'dgptm_get_poll_details',poll_id:pid},function(r){
                  if(r&&r.success&&r.data&&r.data.html){
                    $box.html(r.data.html).data('loaded',1);
                    $row.show();
                  }else{$box.html('<div style="color:var(--mp-red);">Fehler.</div>');$row.show();}
                },'json');
              }else{$row.toggle();}
            }

            $(document).on('click','.loadDetailsBtn',function(e){
              e.preventDefault();
              var pid=$(this).data('id');
              loadDetails(pid,$(this).closest('tr').next('.poll-details'),$('#pollDetails_'+pid));
            });

            // QR toggle in details
            $(document).on('click','.mp-qr-toggle',function(e){
              e.preventDefault();
              var pid=$(this).data('pid');
              var $qr=$('#qrSection_'+pid);
              $qr.toggleClass('open');
              if($qr.hasClass('open'))setTimeout(function(){window.dgptmDrawQR(pid);},10);
            });

            // ===== Delegierte Handler für Poll-Details =====
            $(document).on('change','.resultReleaseSwitch',function(){
              var qid=$(this).data('qid'),chk=$(this).is(':checked');
              $.post(dgptm_ajax.ajax_url,{action:'dgptm_release_poll_results',question_id:qid},function(r){
                if(!(r&&r.success)){alert('Fehler');$('.resultReleaseSwitch[data-qid="'+qid+'"]').prop('checked',!chk);}
              },'json');
            });
            $(document).on('change','.overallSwitch',function(){
              var qid=$(this).data('qid'),chk=$(this).is(':checked');
              $.post(dgptm_ajax.ajax_url,{action:'dgptm_toggle_in_overall',question_id:qid},function(r){
                if(!(r&&r.success)){alert('Fehler');$('.overallSwitch[data-qid="'+qid+'"]').prop('checked',!chk);}
              },'json');
            });
            $(document).on('change','.beamerDisplaySwitch',function(){
              var qid=$(this).data('qid'),pid=$(this).data('pid'),chk=$(this).is(':checked');
              $.post(dgptm_ajax.ajax_url,{action:'dgptm_set_beamer_state',mode:'results_one',question_id:qid,poll_id:pid},function(r){
                if(r&&r.success)updateBeamerBadge('Einzelergebnis');
                else{alert('Fehler: '+(r?r.data:''));$('.beamerDisplaySwitch[data-qid="'+qid+'"]').prop('checked',!chk);}
              },'json');
            });
            $(document).on('click','.questionCtrlBtn',function(e){
              e.preventDefault();
              var act=$(this).data('action'),qid=$(this).data('qid');
              $.post(dgptm_ajax.ajax_url,{action:act,question_id:qid},function(r){
                if(r&&r.success){
                  // Reload details of the parent poll
                  var $qcard=$(document.querySelector('.questionCtrlBtn[data-qid="'+qid+'"]')).closest('.mp-detail');
                  if($qcard.length){var pid=$qcard.find('.addQuestionForm input[name="poll_id"]').val();if(pid){$('#pollDetails_'+pid).data('loaded',0);loadDetails(pid);}}
                  else location.reload();
                }else alert('Fehler: '+(r?r.data:''));
              },'json');
            });
            $(document).on('submit','.addQuestionForm',function(e){
              e.preventDefault();
              $.post(dgptm_ajax.ajax_url,$(this).serialize()+'&action=dgptm_add_poll_question',function(r){
                if(r&&r.success)location.reload();
                else alert('Fehler: '+(r?r.data:''));
              },'json');
            });
            $(document).on('click','.deleteQuestionBtn',function(e){
              e.preventDefault();
              if(!confirm('Frage wirklich löschen?'))return;
              var qid=$(this).data('qid');
              $.post(dgptm_ajax.ajax_url,{action:'dgptm_delete_poll_question',question_id:qid},function(r){
                if(r&&r.success)location.reload();
                else alert('Fehler: '+(r?r.data:''));
              },'json');
            });
            $(document).on('submit','.updateQuestionForm',function(e){
              e.preventDefault();
              var qid=$(this).data('qid');
              $.post(dgptm_ajax.ajax_url,$(this).serialize()+'&question_id='+qid+'&action=dgptm_update_poll_question',function(r){
                if(r&&r.success)alert('Gespeichert.');
                else alert('Fehler: '+(r?r.data:''));
              },'json');
            });
            $(document).on('click','.showParticipantsBtn',function(e){
              e.preventDefault();
              var pid=$(this).data('pid'),$a=$('#participantsArea_'+pid);
              if($a.is(':visible')){$a.slideUp();return;}
              $.post(dgptm_ajax.ajax_url,{action:'dgptm_get_poll_participants',poll_id:pid},function(r){
                if(r&&r.success)$a.html(r.data.html).slideDown();
                else alert('Fehler');
              },'json');
            });
            $(document).on('click','.showVotesBtn',function(e){
              e.preventDefault();
              var qid=$(this).data('qid'),$a=$('#votesArea_'+qid);
              if($a.is(':visible')){$a.slideUp();return;}
              $.post(dgptm_ajax.ajax_url,{action:'dgptm_list_votes',question_id:qid},function(r){
                if(r&&r.success)$a.html(r.data.html).slideDown();
                else alert('Fehler');
              },'json');
            });
            $(document).on('click','.toggleInvalidBtn',function(e){
              e.preventDefault();
              var vid=$(this).data('vid'),qid=$(this).data('qid');
              $.post(dgptm_ajax.ajax_url,{action:'dgptm_toggle_vote_invalid',vote_id:vid},function(r){
                if(r&&r.success){
                  $.post(dgptm_ajax.ajax_url,{action:'dgptm_list_votes',question_id:qid},function(rr){
                    if(rr&&rr.success)$('#votesArea_'+qid).html(rr.data.html);
                  },'json');
                }else alert('Fehler');
              },'json');
            });
            $(document).on('change','.beamerContentToggle',function(){
              var pid=$(this).data('pid'),chk=$(this).is(':checked');
              $.post(dgptm_ajax.ajax_url,{action:'dgptm_toggle_beamer_content',poll_id:pid,active:chk?1:0},function(r){
                if(r&&r.success){updateBeamerBadge(chk?'Pause-Text':'Live');$('#beamerContentGlobal').prop('checked',chk);}
                else{alert('Fehler');$('.beamerContentToggle[data-pid="'+pid+'"]').prop('checked',!chk);}
              },'json');
            });

            // WYSIWYG editor commands
            $(document).on('click','.mp-wysiwyg-cmd',function(e){
              e.preventDefault();
              var cmd=$(this).data('cmd'),val=$(this).data('val')||null;
              document.execCommand(cmd,false,val);
            });
            $(document).on('click','.mp-wysiwyg-img',function(e){
              e.preventDefault();
              var url=prompt('Bild-URL:');
              if(url)document.execCommand('insertImage',false,url);
            });
            $(document).on('click','.mp-wysiwyg-src',function(e){
              e.preventDefault();
              var pid=$(this).data('pid');
              var $editor=$('#beamerWysiwyg_'+pid),$ta=$('#beamerContent_'+pid);
              if($ta.is(':visible')){
                $editor.html($ta.val()).show();$ta.hide();$(this).text('</>')
              }else{
                $ta.val($editor.html()).show();$editor.hide();$(this).text('Vorschau');
              }
            });
            // Sync WYSIWYG to textarea before save
            $(document).on('click','.saveBeamerContentBtn',function(e){
              e.preventDefault();
              var pid=$(this).data('pid');
              var $editor=$('#beamerWysiwyg_'+pid),$ta=$('#beamerContent_'+pid);
              if($editor.is(':visible'))$ta.val($editor.html());
              var content=$ta.val();
              $.post(dgptm_ajax.ajax_url,{action:'dgptm_save_beamer_content',poll_id:pid,beamer_content:content},function(r){
                if(r&&r.success)alert('Gespeichert.');
                else alert('Fehler');
              },'json');
            });

            // Edit question toggle
            $(document).on('click','.editQuestionToggle',function(e){
              e.preventDefault();
              var qid=$(this).data('qid');
              $('#editPanel_'+qid).slideToggle(150);
            });
            // Add option row
            $(document).on('click','.mp-add-option',function(e){
              e.preventDefault();
              var $list=$(this).prev('.mp-options-list');
              if(!$list.length) $list=$(this).siblings('.mp-options-list');
              var num=$list.find('.mp-option-row').length+1;
              $list.append('<div class="mp-option-row" style="display:flex;gap:4px;align-items:center;margin:3px 0;"><span style="font-size:10px;color:var(--mp-gray,#64748b);width:14px;text-align:right;">'+num+'.</span><input type="text" name="opt_text[]" placeholder="Antwort" style="flex:2;font-size:12px;padding:3px 6px;border:1px solid var(--mp-border,#e2e8f0);border-radius:4px;"><input type="text" name="opt_img[]" placeholder="Bild-URL (optional)" style="flex:2;font-size:12px;padding:3px 6px;border:1px solid var(--mp-border,#e2e8f0);border-radius:4px;"><button type="button" class="mp-btn mp-btn-d mp-remove-option" style="padding:1px 5px;">\u2212</button></div>');
            });
            // Remove option row
            $(document).on('click','.mp-remove-option',function(e){
              e.preventDefault();
              var $list=$(this).closest('.mp-options-list');
              if($list.find('.mp-option-row').length>1)$(this).closest('.mp-option-row').remove();
              // Renumber
              $list.find('.mp-option-row').each(function(i){$(this).find('span:first').text((i+1)+'.');});
            });
            // Clear all votes for a question
            $(document).on('click','.clearVotesBtn',function(e){
              e.preventDefault();
              var qid=$(this).data('qid'),cnt=$(this).data('count');
              if(!confirm('Wirklich alle '+cnt+' Stimmen fuer diese Frage loeschen? Dies kann nicht rueckgaengig gemacht werden!'))return;
              $.post(dgptm_ajax.ajax_url,{action:'dgptm_clear_question_votes',question_id:qid},function(r){
                if(r&&r.success){alert(r.data);location.reload();}
                else alert('Fehler: '+(r?r.data:''));
              },'json');
            });

            // Auto-open active poll
            (function(){
              var $ar=$('.poll-header[data-active="1"]').first();
              if($ar.length){
                var pid=$ar.data('poll-id');
                var $dr=$ar.next('.poll-details');
                var $box=$('#pollDetails_'+pid);
                $dr.show();
                if($box.data('loaded')!=1){
                  $box.html('<div style="padding:8px;color:var(--mp-gray);">Lade …</div>');
                  $.post(dgptm_ajax.ajax_url,{action:'dgptm_get_poll_details',poll_id:pid},function(r){
                    if(r&&r.success&&r.data&&r.data.html)$box.html(r.data.html).data('loaded',1);
                    else $box.html('<div style="color:var(--mp-red);">Fehler.</div>');
                  },'json');
                }
              }
            })();
          });
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}

if (!shortcode_exists('manage_poll')) {
    add_action('init', function(){ add_shortcode('manage_poll','dgptm_manage_poll'); }, 5);
}
