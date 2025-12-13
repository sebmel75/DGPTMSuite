<?php
// File: includes/public/member-vote.php

if (!defined('ABSPATH')) exit;

/**
 * Shortcode: [member_vote]
 * - Responsives UI
 * - "Jetzt teilnehmen" Button (erst nach Login oder Name-Gate freischalten)
 * - TN in Anwesenheitsliste (Teilnehmer-Tabelle) dokumentieren; Duplikate vermeiden
 * - Auswahl bleibt während Auto-Refresh erhalten (localStorage + kein DOM-Replacement bei gleicher Frage)
 */
if (!function_exists('dgptm_member_vote')) {
    function dgptm_member_vote(){
        $poll_id = isset($_GET['poll_id']) ? intval($_GET['poll_id']) : 0;
        $token   = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
        ob_start(); ?>
        <style>
          #dgptm_memberVoteContainer{max-width:860px;margin:10px auto;padding:12px;}
          #dgptm_memberVoteContainer .btn{display:inline-block;padding:10px 14px;border:1px solid #333;border-radius:10px;background:#2d6cdf;color:#fff;font-weight:700;cursor:pointer}
          #dgptm_memberVoteContainer .btn:hover{filter:brightness(1.05)}
          #dgptm_memberVoteContainer .cta{font-size: clamp(22px, 4.5vw, 36px); font-weight: 900; text-align:center; margin: 10px 0 8px;}
          #dgptm_memberVoteContainer .box{background:#fff;border:1px solid #ddd;border-radius:12px;padding:14px}
          #dgptm_memberVoteContainer .muted{color:#666}
          #dgptm_memberVoteArea{margin-top:10px}
          #dgptm_memberVoteContainer .row{display:flex;gap:14px;flex-wrap:wrap;align-items:flex-start}
          #dgptm_memberVoteContainer .col{flex:1 1 380px}
          #dgptm_memberVoteContainer label.choice{display:block;margin:8px 0;padding:10px;border:1px solid #ccc;border-radius:10px}
          #dgptm_memberVoteContainer input[type="radio"],#dgptm_memberVoteContainer input[type="checkbox"]{transform:scale(1.2);margin-right:10px}
          #dgptm_memberSecondsWrap{ text-align:center; margin-top:2px; }
          @media (max-width:520px){ #dgptm_memberVoteContainer{padding:8px;} .row{gap:10px} }
        </style>
        <script>window.dgptm_ajax=window.dgptm_ajax||{ajax_url:"<?php echo esc_js(admin_url('admin-ajax.php')); ?>"};</script>

        <div id="dgptm_memberVoteContainer" class="dgptm-wrap" data-poll-id="<?php echo (int)$poll_id; ?>" data-token="<?php echo esc_attr($token); ?>">
          <div class="cta">Jetzt Abstimmen</div>
          <div id="dgptm_memberSecondsWrap"><small>Sekundenzähler: <span id="dgptm_memberSeconds">0</span> s</small></div>
          <div id="dgptm_memberVoteArea" class="box"><p style="text-align:center;margin:0;">Lade …</p></div>
        </div>

        <script>
        (function waitForDeps(){
          if(typeof jQuery==='undefined' || !window.dgptm_ajax || !window.dgptm_ajax.ajax_url){ return setTimeout(waitForDeps,50); }
          jQuery(function($){
            var loopTimer=null, questionActive=false, memberSeconds=0;
            var currentQuestionId=null, joined=false, booted=false;

            function lsKey(qid){ return "dgptm_sel_"+qid; }
            function saveSelection(qid){
              var vals=[]; $("#dgptm_memberVoteForm input[name='choices[]']:checked").each(function(){ vals.push($(this).val()); });
              try{ localStorage.setItem(lsKey(qid), JSON.stringify(vals)); }catch(e){}
              return vals;
            }
            function loadSelection(qid){
              try{
                var raw=localStorage.getItem(lsKey(qid));
                if(!raw) return [];
                var arr=JSON.parse(raw); if(Array.isArray(arr)) return arr;
              }catch(e){}
              return [];
            }
            function applySelection(vals){
              if(!vals || !vals.length) return;
              $("#dgptm_memberVoteForm input[name='choices[]']").each(function(){
                if(vals.indexOf($(this).val())>-1){ $(this).prop('checked',true); }
              });
            }
            function disableForm(dis){
              var $f=$("#dgptm_memberVoteForm");
              if(!$f.length) return;
              $f.find('input,button[type=submit]').prop('disabled', !!dis);
              $("#dgptm_joinWrap").toggle(!!dis);
            }

            function renderHTML(html, qid){
              // Wenn gleiche Frage bereits angezeigt, DOM nicht ersetzen (damit Auswahl erhalten bleibt)
              var $existing=$("#dgptm_memberVoteForm");
              if($existing.length && parseInt($existing.data("qid"),10)===parseInt(qid,10)){
                return; // nichts ersetzen, Auswahl bleibt
              }
              $("#dgptm_memberVoteArea").html(html);
              applyFormLogic();
              var stored=loadSelection(qid); applySelection(stored);
            }

            function loadMember(){
              var pid = $("#dgptm_memberVoteContainer").data("poll-id") || '';
              var token = $("#dgptm_memberVoteContainer").data("token") || '';
              $.ajax({
                url:dgptm_ajax.ajax_url, type:"POST", dataType:"json",
                data:{action:"dgptm_get_member_view", poll_id:pid, token: token}
              }).done(function(resp){
                if(resp && resp.success){
                  var qid = resp.data.question_id || null;
                  currentQuestionId = qid;
                  renderHTML(resp.data.html || "<div class='box'><em>Keine aktive Frage.</em></div>", qid);
                  questionActive=true;
                  if(!booted){ disableForm(true); booted=true; }
                } else {
                  if(resp && resp.data && resp.data.html){
                    $("#dgptm_memberVoteArea").html(resp.data.html);
                    applyNameGateHandlers();
                  } else {
                    $("#dgptm_memberVoteArea").html("<div class='box'><em>Keine aktive Frage.</em></div>");
                  }
                  questionActive=false; memberSeconds=0; currentQuestionId=null;
                }
              }).fail(function(){});
            }

            function applyNameGateHandlers(){
              $(document).off("submit","#dgptm_nameGateForm").on("submit","#dgptm_nameGateForm", function(e){
                e.preventDefault();
                var nm = $.trim($("#dgptm_gateName").val());
                if(!nm){ alert("Bitte Name eingeben."); return; }
                var pid = $("#dgptm_memberVoteContainer").data("poll-id") || '';
                document.cookie = "dgptm_participant_name="+encodeURIComponent(nm)+";path=/;max-age=31536000";
                $.post(dgptm_ajax.ajax_url,{action:"dgptm_register_anon_participant", name:nm, poll_id:pid}, function(r){
                  if(r && r.success){ loadMember(); }
                  else { alert("Fehler: "+(r?r.data:'keine Antwort')); }
                },"json");
              });
            }

            function applyFormLogic(){
              var $f=$("#dgptm_memberVoteForm"); if(!$f.length) return;
              var qid=parseInt($f.data("qid"),10)||0;
              var maxv=parseInt($f.data("max-votes"),10)||1;

              // "Jetzt teilnehmen" Button
              $(document).off("click","#dgptm_joinBtn").on("click","#dgptm_joinBtn", function(e){
                e.preventDefault();
                var pid = $("#dgptm_memberVoteContainer").data("poll-id") || '';
                $.post(dgptm_ajax.ajax_url,{action:"dgptm_join_poll", poll_id:pid}, function(r){
                  if(r && r.success){
                    joined=true; disableForm(false);
                    $("#dgptm_memberVoteFeedback").html('<p style="color:#1b7f2a;font-weight:700;">Teilnahme freigeschaltet.</p>');
                  } else {
                    $("#dgptm_memberVoteFeedback").html('<p style="color:#a00;font-weight:700;">'+(r?r.data:'Fehler beim Freischalten')+'</p>');
                  }
                },"json");
              });

              // Auswahl-Speicher
              $f.find('input[name="choices[]"]').on("change input", function(){
                saveSelection(qid);
                if(maxv>1){
                  var count=$f.find('input[type="checkbox"][name="choices[]"]:checked').length;
                  if(count>maxv){ $(this).prop("checked",false); alert("Maximal "+maxv+" Stimmen erlaubt."); }
                }
              });

              // Single-Choice: toggelbar
              if(maxv===1){
                $f.find('input[type="radio"][name="choices[]"]').on("mousedown",function(){
                  if($(this).is(":checked")){ $(this).prop("checked",false); saveSelection(qid); }
                });
              }

              // Submit
              $f.off("submit").on("submit", function(e){
                e.preventDefault();
                if(!joined){ alert("Bitte zuerst 'Jetzt an der Abstimmung teilnehmen' drücken."); return; }
                var data=$f.serialize();
                $.post(dgptm_ajax.ajax_url, data, function(r){
                  if(r && r.success){
                    $("#dgptm_memberVoteFeedback").html('<p style="color:#1b7f2a;font-weight:700;">'+r.data+'</p>');
                  } else {
                    $("#dgptm_memberVoteFeedback").html('<p style="color:#a00;font-weight:700;">'+(r?r.data:'Fehler')+'</p>');
                  }
                },"json");
              });
            }

            function memberLoop(){
              $.ajax({
                url:dgptm_ajax.ajax_url, type:"POST", dataType:"json", data:{action:"dgptm_is_poll_active"}
              }).done(function(r){
                var interval=(r && r.success && r.data.active)?900:3500;
                loopTimer=setTimeout(memberLoop, interval);
              }).fail(function(){ loopTimer=setTimeout(memberLoop,3500); });

              loadMember();

              if(questionActive){ memberSeconds++; $("#dgptm_memberSeconds").text(memberSeconds); }
              else { $("#dgptm_memberSeconds").text("0"); }
            }

            memberLoop();
          });
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}

if (!shortcode_exists('member_vote')) {
    add_shortcode('member_vote','dgptm_member_vote');
}
