<?php
// File: includes/admin/manage-poll.php

if (!defined('ABSPATH')) exit;

/**
 * Manager-Ansicht (Shortcode [manage_poll])
 * - Aktive Umfrage wird automatisch aufgeklappt und Details via AJAX geladen
 * - QR-Code Rendering mit Fallback <img> falls QRCode-Lib nicht verfügbar ist
 * - Robustere Event-Handler & Fehlerbehandlung
 */
if (!function_exists('dgptm_manage_poll')) {
    function dgptm_manage_poll() {
        if (!function_exists('dgptm_is_manager') || !dgptm_is_manager()) {
            return '<p>Keine Berechtigung.</p>';
        }

        global $wpdb;
        $polls = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_polls ORDER BY created DESC");
        $beamer_state = json_decode(get_option('dgptm_beamer_state', wp_json_encode(array('mode'=>'auto'))), true);
        if(!is_array($beamer_state)) $beamer_state = array('mode'=>'auto');

        ob_start(); ?>
        <style>
          .poll-table{width:100%;border-collapse:collapse;margin:15px 0;}
          .poll-table th,.poll-table td{border:1px solid #ccc;padding:8px;text-align:left;}
          .poll-header{background:#f9f9f9;}
          .switch{position:relative;display:inline-block;width:46px;height:24px;vertical-align:middle;}
          .switch input{display:none;}
          .slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#ccc;transition:.2s;border-radius:24px;}
          .slider:before{position:absolute;content:"";height:18px;width:18px;left:3px;bottom:3px;background:#fff;transition:.2s;border-radius:50%;}
          input:checked + .slider{background:#4CAF50;}
          input:checked + .slider:before{transform:translateX(22px);}
          .muted{opacity:.8}
          .btn{display:inline-block;padding:8px 12px;border:1px solid #666;border-radius:8px;background:#2d6cdf;color:#fff;cursor:pointer;margin:2px 0;font-weight:600;}
          .btn:hover{filter:brightness(1.05)}
          .qrcode-canvas{border:1px dashed #ccc;padding:10px;border-radius:6px;background:#fff}
          .dgptm-modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;align-items:center;justify-content:center;z-index:99999;}
          .dgptm-modal{background:#fff;max-width:920px;width:95%;max-height:85vh;overflow:auto;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.25);}
          .dgptm-modal header{position:sticky;top:0;background:#fafafa;border-bottom:1px solid #ddd;padding:10px 14px;border-top-left-radius:12px;border-top-right-radius:12px;display:flex;align-items:center;justify-content:space-between}
          .dgptm-modal .content{padding:14px}
          .dgptm-x{border:0;background:#fff;border:1px solid #ccc;border-radius:8px;padding:6px 10px;cursor:pointer}
        </style>

        <script>
          // Fallback: dgptm_ajax
          window.dgptm_ajax = window.dgptm_ajax || { ajax_url: "<?php echo esc_js(admin_url('admin-ajax.php')); ?>" };
        </script>
        <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>

        <div class="manage-poll" id="dgptm_managePoll">
          <h2>Umfragen verwalten</h2>
          <div style="margin:8px 0 14px;display:flex;gap:8px;flex-wrap:wrap;">
            <button class="btn" id="dgptm_openSettings">Einstellungen</button>
            <button id="newPollBtn" class="btn">Neue Umfrage anlegen</button>
          </div>

          <!-- Modal: Einstellungen – Inhalt wird lazy per AJAX geladen -->
          <div id="dgptm_settingsBackdrop" class="dgptm-modal-backdrop" aria-hidden="true">
            <div class="dgptm-modal" role="dialog" aria-modal="true" aria-labelledby="dgptm_settingsTitle">
              <header>
                <strong id="dgptm_settingsTitle">Einstellungen</strong>
                <button class="dgptm-x" id="dgptm_closeSettings">Schließen</button>
              </header>
              <div class="content" id="dgptm_settingsContent">
                <div style="text-align:center;padding:18px;"><em>Lade Einstellungen …</em></div>
              </div>
            </div>
          </div>

          <div id="newPollForm" style="display:none;margin-top:10px;">
            <h3>Neue Umfrage</h3>
            <form id="createPollForm">
              <label>Umfragename: <input type="text" name="poll_name" required></label><br><br>
              <label>Benutzer müssen sich anmelden? <input type="checkbox" name="requires_signup"></label><br><br>
              <label>Logo-URL (jpg/jpeg/png möglich):<br><input type="text" name="poll_logo_url" placeholder="https://..."></label><br><br>
              <button type="submit" class="btn">Umfrage anlegen</button>
            </form>
          </div>

          <table class="poll-table">
            <thead><tr><th>ID</th><th>Name</th><th>Status</th><th>Aktiv/Archiv?</th><th>Beamer: Alle Ergebnisse</th><th>Details</th></tr></thead>
            <tbody>
            <?php
            if($polls):
              foreach($polls as $poll):
                $is_active = ($poll->status==='active');
                $state = $beamer_state;
                $all_on = (is_array($state) && isset($state['mode']) && $state['mode']==='results_all' && !empty($state['poll_id']) && (int)$state['poll_id']===(int)$poll->id);
            ?>
              <tr class="poll-header" data-poll-id="<?php echo (int)$poll->id; ?>" data-active="<?php echo $is_active?1:0; ?>">
                <td><?php echo (int)$poll->id; ?></td>
                <td><?php echo esc_html($poll->name); ?></td>
                <td><?php echo esc_html($poll->status); ?></td>
                <td>
                  <label class="switch">
                    <input type="checkbox" class="pollToggleSwitch" data-id="<?php echo (int)$poll->id; ?>" <?php checked($is_active); ?>>
                    <span class="slider round"></span>
                  </label>
                </td>
                <td>
                  <label class="switch" title="Beamer: Alle beendeten, freigegebenen Fragen dieser Umfrage anzeigen/verbergen">
                    <input type="checkbox" class="beamerAllSwitch" data-pid="<?php echo (int)$poll->id; ?>" <?php checked($all_on); ?>>
                    <span class="slider round"></span>
                  </label>
                </td>
                <td><button class="btn loadDetailsBtn" data-id="<?php echo (int)$poll->id; ?>">Details</button></td>
              </tr>
              <tr class="poll-details" style="display:none;">
                <td colspan="6"><div id="pollDetails_<?php echo (int)$poll->id; ?>" data-loaded="0"></div></td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="6">Keine Umfragen gefunden</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>

        <script>
        (function waitForDeps(){
          if(typeof jQuery==='undefined' || !window.dgptm_ajax || !window.dgptm_ajax.ajax_url){
            return setTimeout(waitForDeps,50);
          }
          jQuery(function($){

            function enc(s){ try{return encodeURIComponent(s);}catch(e){return s;} }

            // QR zeichnen – mit Fallback auf <img>, falls QRCode-Lib nicht verfügbar
            window.dgptmDrawQR = function(pid){
              try{
                var el = document.getElementById('qrCanvas_'+pid);
                if(!el) return;
                var urlNode = document.getElementById('qrUrlText_'+pid);
                if(!urlNode) return;
                var dataUrl = urlNode.getAttribute('data-qr') || urlNode.textContent || urlNode.innerText || '';
                if(typeof QRCode!=='undefined' && QRCode.toCanvas){
                  QRCode.toCanvas(el, dataUrl, {width:260, margin:1, errorCorrectionLevel:'M'});
                  return;
                }
                // Fallback <img>
                var img = new Image();
                img.alt = 'QR';
                img.width = 260; img.height = 260;
                img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=' + enc(dataUrl);
                el.replaceWith(img);
              }catch(e){}
            };

            window.dgptmDownloadQR = function(pid){
              var c=document.getElementById('qrCanvas_'+pid);
              if(!c){ dgptmDrawQR(pid); c=document.getElementById('qrCanvas_'+pid); }
              if(!c) return;
              // Wenn Fallback-IMG vorhanden ist, lade direkt dessen src herunter
              if(c.tagName && c.tagName.toLowerCase()==='img'){
                var a=document.createElement('a');
                a.href=c.src; a.download='qr_poll_'+pid+'.png'; a.click(); return;
              }
              var a=document.createElement('a');
              a.href=c.toDataURL('image/png');
              a.download='qr_poll_'+pid+'.png';
              a.click();
            };

            // Modal öffnen/schließen + Inhalt lazy laden
            $("#dgptm_openSettings").on("click", function(){
              $("#dgptm_settingsBackdrop").fadeIn(120).attr('aria-hidden','false');
              $("#dgptm_settingsContent").html('<div style="text-align:center;padding:18px;"><em>Lade Einstellungen …</em></div>');
              jQuery.post(dgptm_ajax.ajax_url,{action:'dgptm_get_manager_settings'},function(r){
                if(r && r.success && r.data && r.data.html){ $("#dgptm_settingsContent").html(r.data.html); }
                else { $("#dgptm_settingsContent").html('<div style="color:#a00;">Fehler beim Laden</div>'); }
              },'json');
            });
            $("#dgptm_closeSettings").on("click", function(){ $("#dgptm_settingsBackdrop").fadeOut(120).attr('aria-hidden','true'); });
            $(document).on("keydown", function(e){ if(e.key==='Escape'){ $("#dgptm_closeSettings").trigger("click"); } });

            $("#newPollBtn").on("click", function(e){ e.preventDefault(); $("#newPollForm").toggle(); });
            $("#createPollForm").on("submit", function(e){
              e.preventDefault();
              $.post(dgptm_ajax.ajax_url, $(this).serialize()+"&action=dgptm_create_poll", function(resp){
                if(resp && resp.success){ alert("Umfrage angelegt."); location.reload(); }
                else { alert("Fehler: "+(resp?resp.data:'keine Antwort')); }
              },"json");
            });

            // Aktiv/Archiv Toggle
            $(document).on("change",".pollToggleSwitch", function(){
              var pid=$(this).data("id");
              $.post(dgptm_ajax.ajax_url,{action:"dgptm_toggle_poll_status", poll_id:pid}, function(resp){
                if(resp && resp.success){ alert("Neuer Status: "+resp.data.new_status); location.reload(); }
                else { alert("Fehler: "+(resp?resp.data:'keine Antwort')); }
              },"json");
            });

            // Beamer: Alle Ergebnisse Toggle
            $(document).on("change",".beamerAllSwitch", function(){
              var pid=$(this).data("pid"), checked=$(this).is(":checked");
              $.post(dgptm_ajax.ajax_url,{action:"dgptm_toggle_beamer_all_for_poll", poll_id:pid}, function(r){
                if(!(r && r.success)){
                  alert("Fehler: "+(r?r.data:'keine Antwort'));
                  $('.beamerAllSwitch[data-pid="'+pid+'"]').prop('checked', !checked);
                }
              },"json");
            });

            // Details lazy nachladen
            function loadDetails(pid, $row, $box){
              if(!$row || !$row.length){ $row = $('.poll-header[data-poll-id="'+pid+'"]').next('.poll-details'); }
              if(!$box || !$box.length){ $box = $("#pollDetails_"+pid); }
              if($box.data('loaded')!=1){
                $box.html('<div style="padding:10px;"><em>Lade Details …</em></div>');
                $.post(dgptm_ajax.ajax_url,{action:"dgptm_get_poll_details", poll_id:pid}, function(r){
                  if(r && r.success && r.data && r.data.html){
                    $box.html(r.data.html).data('loaded',1);
                    $row.show();
                    // QR zeichnen (mit Fallback)
                    setTimeout(function(){ window.dgptmDrawQR(pid); }, 10);
                  } else {
                    $box.html('<div style="color:#a00;">Fehler beim Laden der Details.</div>');
                    $row.show();
                  }
                },"json");
              } else {
                $row.toggle();
              }
            }

            $(document).on("click",".loadDetailsBtn", function(e){
              e.preventDefault();
              var pid=$(this).data('id');
              var $row=$(this).closest('tr').next('.poll-details');
              var $box=$("#pollDetails_"+pid);
              loadDetails(pid, $row, $box);
            });

            /* ===== Delegierte Handler ===== */
            $(document).on("submit","#dgptm_beamerSettingsForm", function(e){
              e.preventDefault();
              $.post(dgptm_ajax.ajax_url, $(this).serialize()+"&action=dgptm_save_beamer_settings", function(resp){
                alert(resp && resp.success? "Einstellungen gespeichert." : "Fehler: "+(resp?resp.data:'keine Antwort'));
              },"json");
            });
            $(document).on("change","#beamerQrSwitch", function(){
              var checked=$(this).is(":checked");
              $.post(dgptm_ajax.ajax_url,{action:"dgptm_toggle_beamer_qr", visible: checked?1:0}, function(r){
                if(!(r && r.success)){
                  alert("Fehler: "+(r?r.data:'keine Antwort'));
                  $("#beamerQrSwitch").prop('checked', !checked);
                }
              },"json");
            });
            $(document).on("click","#beamerAuto", function(){
              $.post(dgptm_ajax.ajax_url,{action:"dgptm_set_beamer_state", mode:"auto"}, function(r){
                alert(r && r.success? "Beamer zeigt Live-Anzeige." : "Fehler: "+(r?r.data:'keine Antwort'));
              },"json");
            });
            $(document).on("click","#beamerOverall", function(){
              $.post(dgptm_ajax.ajax_url,{action:"dgptm_set_beamer_state_overall"}, function(r){
                alert(r && r.success? "Gesamtstatistik im Beamer aktiv." : "Fehler: "+(r?r.data:'keine Antwort'));
              },"json");
            });

            $(document).on("submit",".addQuestionForm", function(e){
              e.preventDefault();
              $.post(dgptm_ajax.ajax_url, $(this).serialize()+"&action=dgptm_add_poll_question", function(resp){
                if(resp && resp.success){ alert("Frage angelegt."); location.reload(); }
                else { alert("Fehler: "+(resp?resp.data:'keine Antwort')); }
              },"json");
            });
            $(document).on("click",".deleteQuestionBtn", function(e){
              e.preventDefault();
              if(!confirm("Frage wirklich löschen?")) return;
              var qid=$(this).data("qid");
              $.post(dgptm_ajax.ajax_url,{action:"dgptm_delete_poll_question", question_id:qid}, function(resp){
                if(resp && resp.success){ alert("Frage gelöscht."); location.reload(); }
                else { alert("Fehler: "+(resp?resp.data:'keine Antwort')); }
              },"json");
            });
            $(document).on("change",".resultReleaseSwitch", function(){
              var qid=$(this).data("qid"), checked=$(this).is(":checked");
              $.post(dgptm_ajax.ajax_url,{action:"dgptm_release_poll_results", question_id:qid}, function(r){
                if(!(r && r.success)){
                  alert("Fehler: "+(r?r.data:'keine Antwort'));
                  $('.resultReleaseSwitch[data-qid="'+qid+'"]').prop('checked', !checked);
                }
              },"json");
            });
            $(document).on("change",".overallSwitch", function(){
              var qid=$(this).data("qid"), checked=$(this).is(":checked");
              $.post(dgptm_ajax.ajax_url,{action:"dgptm_toggle_in_overall", question_id:qid}, function(r){
                if(!(r && r.success)){
                  alert("Fehler: "+(r?r.data:'keine Antwort'));
                  $('.overallSwitch[data-qid="'+qid+'"]').prop('checked', !checked);
                }
              },"json");
            });
            $(document).on("change",".beamerDisplaySwitch", function(){
              var qid=$(this).data("qid"), pid=$(this).data("pid"), checked=$(this).is(":checked");
              $.post(dgptm_ajax.ajax_url,{action:"dgptm_set_beamer_state", mode:"results_one", question_id:qid, poll_id:pid}, function(r){
                if(!(r && r.success)){
                  alert("Fehler: "+(r?r.data:'keine Antwort'));
                  $('.beamerDisplaySwitch[data-qid="'+qid+'"]').prop('checked', !checked);
                }
              },"json");
            });
            $(document).on("click",".questionCtrlBtn", function(e){
              e.preventDefault();
              var action=$(this).data("action"), qid=$(this).data("qid");
              $.post(dgptm_ajax.ajax_url,{action:action, question_id:qid}, function(r){
                if(r && r.success){ alert("Aktion erfolgreich."); location.reload(); }
                else { alert("Fehler: "+(r?r.data:'keine Antwort')); }
              },"json");
            });
            $(document).on("submit",".updateQuestionForm", function(e){
              e.preventDefault();
              var qid=$(this).data("qid");
              var data=$(this).serialize()+"&question_id="+qid+"&action=dgptm_update_poll_question";
              $.post(dgptm_ajax.ajax_url,data,function(r){
                if(r && r.success){ alert("Frage aktualisiert."); }
                else { alert("Fehler: "+(r?r.data:'keine Antwort')); }
              },"json");
            });
            $(document).on("click",".showParticipantsBtn", function(e){
              e.preventDefault();
              var pid=$(this).data("pid"), $area=$("#participantsArea_"+pid);
              if($area.is(":visible")) { $area.slideUp(); return; }
              $.post(dgptm_ajax.ajax_url,{action:"dgptm_get_poll_participants",poll_id:pid},function(r){
                if(r && r.success){ $area.html(r.data.html).slideDown(); }
                else { alert("Fehler: "+(r?r.data:'keine Antwort')); }
              },"json");
            });
            $(document).on("click",".showVotesBtn", function(e){
              e.preventDefault();
              var qid=$(this).data("qid"), $area=$("#votesArea_"+qid);
              if($area.is(":visible")) { $area.slideUp(); return; }
              $.post(dgptm_ajax.ajax_url,{action:"dgptm_list_votes", question_id:qid}, function(r){
                if(r && r.success){ $area.html(r.data.html).slideDown(); }
                else { alert("Fehler: "+(r?r.data:'keine Antwort')); }
              },"json");
            });
            $(document).on("click",".toggleInvalidBtn", function(e){
              e.preventDefault();
              var vid=$(this).data("vid"), qid=$(this).data("qid");
              $.post(dgptm_ajax.ajax_url,{action:"dgptm_toggle_vote_invalid", vote_id:vid}, function(r){
                if(r && r.success){
                  $.post(dgptm_ajax.ajax_url,{action:"dgptm_list_votes", question_id:qid}, function(rr){
                    if(rr && rr.success){ $("#votesArea_"+qid).html(rr.data.html); }
                  },"json");
                } else { alert("Fehler: "+(r?r.data:'keine Antwort')); }
              },"json");
            });

            // Registrierungseinstellungen speichern & Liste anzeigen (delegiert)
            $(document).on("submit","#dgptm_registrationForm", function(e){
              e.preventDefault();
              var $f=$(this);
              var data={
                action:'dgptm_save_registration_settings',
                enabled: $f.find('input[name="enabled"]').is(':checked') ? 1 : 0,
                poll_id: $f.find('input[name="poll_id"]').val(),
                endpoint: $f.find('input[name="endpoint"]').val(),
                mail_subject: $f.find('input[name="mail_subject"]').val(),
                mail_body: $f.find('textarea[name="mail_body"]').val()
              };
              $.post(dgptm_ajax.ajax_url, data, function(r){
                alert(r && r.success ? 'Gespeichert.' : ('Fehler: '+(r?r.data:'keine Antwort')));
              }, 'json');
            });
            $(document).on("click","#dgptm_showRegistered", function(e){
              e.preventDefault();
              $.post(dgptm_ajax.ajax_url, {action:'dgptm_get_registered_members'}, function(r){
                if(r && r.success){ $("#dgptm_registeredList").html(r.data.html); }
                else { alert('Fehler: '+(r?r.data:'keine Antwort')); }
              }, 'json');
            });

            // ======= Neu: aktive Umfrage automatisch aufklappen =======
            (function autoOpenActive(){
              var $activeRow = $('.poll-header[data-active="1"]').first();
              if($activeRow.length){
                var pid = $activeRow.data('poll-id');
                var $detailsRow = $activeRow.next('.poll-details');
                var $box = $("#pollDetails_"+pid);
                // Sofort laden & anzeigen
                $detailsRow.show();
                if($box.data('loaded') != 1){
                  $box.html('<div style="padding:10px;"><em>Lade Details …</em></div>');
                  $.post(dgptm_ajax.ajax_url,{action:"dgptm_get_poll_details", poll_id:pid}, function(r){
                    if(r && r.success && r.data && r.data.html){
                      $box.html(r.data.html).data('loaded',1);
                      setTimeout(function(){ window.dgptmDrawQR(pid); }, 10);
                    } else {
                      $box.html('<div style="color:#a00;">Fehler beim Laden der Details.</div>');
                    }
                  }, 'json');
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

// Failsafe-Registrierung des Shortcodes (früh laden)
if (!shortcode_exists('manage_poll')) {
    add_action('init', function(){
        add_shortcode('manage_poll','dgptm_manage_poll');
    }, 5);
}
