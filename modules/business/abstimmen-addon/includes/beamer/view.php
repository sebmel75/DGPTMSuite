<?php
// File: includes/beamer/view.php

if (!defined('ABSPATH')) exit;

/**
 * Shortcode: [beamer_view]
 * - Zeigt Verhältnis "Abgegebene Stimmen / Anwesende Mitglieder" an (live)
 * - QR oben rechts optional; Chart.js wie gehabt
 */
if (!function_exists('dgptm_beamer_view')) {
    function dgptm_beamer_view() {
        if (!function_exists('dgptm_is_manager') || !dgptm_is_manager()) return '<p>Keine Berechtigung.</p>';

        $no_poll_text = get_option('dgptm_no_poll_text','Bitte warten …');
        ob_start(); ?>
        <style>
          html,body{height:100%;background:#fff;}
          #dgptm_beamerContainer{width:100%;height:100vh;position:relative;overflow:hidden;background:#fff;}
          #dgptm_beamerClockBox{position:absolute;top:10px;left:10px;background:rgba(0,0,0,.55);color:#fff;padding:6px 10px;border-radius:6px;font-size:14px;}
          #dgptm_beamerPollName{position:absolute;top:10px;right:10px;background:rgba(0,0,0,.55);color:#fff;padding:6px 10px;border-radius:6px;font-size:16px;display:none;}
          #dgptm_beamerLogo{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);max-width:60%;max-height:60%;display:none;}
          #dgptm_beamerFillerText{position:absolute;top:65%;left:50%;transform:translate(-50%,-50%);text-align:center;font-size:24px;color:#444;display:none;}
          #dgptm_beamerQuestionArea{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);display:none;width:80%;max-width:1000px;text-align:center;}
          #dgptm_callToVote{font-size: clamp(24px, 5vw, 46px);font-weight:900;margin:0 0 8px;}
          #dgptm_questionTitle{font-size: clamp(20px, 4vw, 34px); margin:0;}
          #dgptm_liveBar{margin-top:8px;font-size:18px;display:none;}
          #dgptm_beamerChart{margin-top:15px;width:100%;max-width:1000px;height:460px;display:none;}
          #dgptm_resultsGrid{position:absolute;left:50%;top:55%;transform:translate(-50%,-50%);display:none;width:92%;max-width:1400px;}
          .resRow{display:flex;gap:20px;flex-wrap:wrap;justify-content:center;}
          .resCard{flex:1 1 360px;max-width:520px;border:1px solid #ddd;padding:10px;border-radius:8px;margin:8px;}
          .resCard canvas{width:100%;height:320px;}
          #dgptm_qrBox{position:absolute;right:14px;top:14px;background:rgba(255,255,255,.96);border:1px solid #ddd;border-radius:8px;padding:8px 10px;display:none;text-align:center;}
          #dgptm_qrBox canvas{display:block;margin:auto;width:180px !important;height:180px !important;}
          #dgptm_qrBox small{display:block;margin-top:4px;color:#333}
        </style>

        <script>window.dgptm_ajax=window.dgptm_ajax||{ajax_url:"<?php echo esc_js(admin_url('admin-ajax.php')); ?>"};</script>

        <div id="dgptm_beamerContainer">
          <div id="dgptm_beamerClockBox"><span id="dgptm_clockTime">00:00:00</span> | <span id="dgptm_questionSeconds">0s</span></div>
          <div id="dgptm_beamerPollName"></div>
          <img id="dgptm_beamerLogo" src="" alt="Logo" style="display:none;">
          <div id="dgptm_beamerFillerText"><?php echo esc_html($no_poll_text); ?></div>

          <div id="dgptm_beamerQuestionArea">
            <div id="dgptm_callToVote">Bitte Abstimmen</div>
            <h2 id="dgptm_questionTitle"></h2>
            <div id="dgptm_liveBar"></div>
            <canvas id="dgptm_beamerChart"></canvas>
          </div>

          <div id="dgptm_resultsGrid"></div>

          <div id="dgptm_qrBox">
            <canvas id="dgptm_qrCanvas" width="180" height="180"></canvas>
            <small>Jetzt teilnehmen</small>
          </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
        (function waitForDeps(){
          if(typeof jQuery==='undefined' || typeof window.dgptm_ajax==='undefined' || !window.dgptm_ajax.ajax_url){
            return setTimeout(waitForDeps,50);
          }
          jQuery(function($){
            var questionActive=false, currentPollId=0, questionSeconds=0, refreshTimer=null;
            var lastMode='', showQR=false, lastLogo='', lastTitle='', lastLiveCount=-1, lastAttendees=-1;

            function pad(n){return String(n).padStart(2,"0");}
            function updateClock(){
              var now=new Date();
              $("#dgptm_clockTime").text(pad(now.getHours())+":"+pad(now.getMinutes())+":"+pad(now.getSeconds()));
              if(questionActive){ questionSeconds++; $("#dgptm_questionSeconds").text(questionSeconds+"s"); }
              else { $("#dgptm_questionSeconds").text("0s"); }
              setTimeout(updateClock,1000);
            }
            function schedule(ms){ clearTimeout(refreshTimer); refreshTimer=setTimeout(loadState, ms); }
            function drawQR(url){
              try{
                if(typeof QRCode==='undefined' || !QRCode.toCanvas) return;
                var cv = document.getElementById('dgptm_qrCanvas');
                QRCode.toCanvas(cv, url, {width:180, margin:0, errorCorrectionLevel:'M'});
              }catch(e){}
            }
            function setVisible(id, vis){
              var $el=$(id);
              if(vis){ if(!$el.is(":visible")) $el.show(); }
              else { if($el.is(":visible")) $el.hide(); }
            }
            function liveBar(totalVotes, attendees){
              attendees = attendees || 0;
              var txt = "Abgegebene Stimmen: "+totalVotes+" / Anwesende: "+attendees;
              if(attendees>0){
                var pct = Math.round((totalVotes/attendees)*100);
                txt += " ("+pct+"%)";
              }
              $("#dgptm_liveBar").text(txt);
            }

            function loadState(){
              $.post(dgptm_ajax.ajax_url,{action:"dgptm_get_beamer_payload"},function(resp){
                if(!resp || !resp.success){ schedule(15000); return; }
                var data=resp.data;
                var state = data.beamer_state || {};
                var qrVisible = !!state.qr_visible;

                if(!data.active_poll){
                  setVisible("#dgptm_beamerPollName", false);
                  setVisible("#dgptm_beamerLogo", false);
                  setVisible("#dgptm_beamerFillerText", false);
                  setVisible("#dgptm_beamerQuestionArea", false);
                  setVisible("#dgptm_resultsGrid", false);
                  setVisible("#dgptm_qrBox", false);
                  questionActive=false; questionSeconds=0;
                  schedule(15000); return;
                }

                var attendees = parseInt(data.attendees || 0,10);
                $("#dgptm_beamerPollName").text(data.active_poll.name || '').show();

                if(currentPollId!==data.active_poll.id){
                  currentPollId=data.active_poll.id;
                  lastLogo=''; lastTitle=''; lastLiveCount=-1; lastAttendees=-1; questionSeconds=0;
                }

                if(qrVisible){
                  var url = (window.location.origin || '') + "<?php echo esc_js( add_query_arg(array('dgptm_member'=>1), home_url('/')) ); ?>" + "&poll_id="+currentPollId;
                  setVisible("#dgptm_qrBox", true);
                  if(!showQR){ drawQR(url); showQR=true; }
                } else {
                  setVisible("#dgptm_qrBox", false);
                  showQR=false;
                }

                var mode = state.mode ? state.mode : 'auto';

                if(mode==='results_one' && data.state_question){
                  if(lastMode!=='results_one'){
                    setVisible("#dgptm_beamerLogo", false);
                    setVisible("#dgptm_beamerFillerText", false);
                    setVisible("#dgptm_resultsGrid", false);
                    setVisible("#dgptm_beamerQuestionArea", true);
                    $("#dgptm_liveBar").show();
                    $("#dgptm_beamerChart").show();
                  }
                  $("#dgptm_questionTitle").text(data.state_question.question || "");
                  var tot = 0; try{ (data.state_question.votes||[]).forEach(function(n){ tot += (parseInt(n,10)||0); }); }catch(e){}
                  liveBar(tot, attendees);
                  renderChart("dgptm_beamerChart", data.state_question.choices, data.state_question.votes, data.state_question.chart_type || 'bar');
                  lastMode='results_one';
                  questionActive=false; questionSeconds=0; schedule(1800); return;
                }

                if(mode==='results_all' && data.state_questions && data.state_questions.length){
                  if(lastMode!=='results_all'){
                    setVisible("#dgptm_beamerQuestionArea", true);
                    setVisible("#dgptm_beamerLogo", false);
                    setVisible("#dgptm_beamerFillerText", false);
                    setVisible("#dgptm_resultsGrid", true);
                    $("#dgptm_resultsGrid").empty();
                    var row=$('<div class="resRow"></div>').appendTo("#dgptm_resultsGrid");
                    data.state_questions.forEach(function(q){
                      var tot=0; try{ (q.votes||[]).forEach(function(n){ tot+=(parseInt(n,10)||0); }); }catch(e){}
                      var card=$('<div class="resCard"><h3 class="ttl"></h3><div class="muted" style="margin-top:-6px;margin-bottom:6px;">Abgegebene: '+tot+' / Anwesende: '+attendees+'</div><canvas id="resC'+q.id+'"></canvas></div>');
                      card.find('.ttl').text(q.question);
                      row.append(card);
                      setTimeout(function(){ renderChart('resC'+q.id, q.choices, q.votes, q.chart_type || 'bar'); },10);
                    });
                    $("#dgptm_liveBar").text("Gesamtergebnisse").show();
                  }
                  lastMode='results_all'; questionActive=false; questionSeconds=0; schedule(2500); return;
                }

                // Live-Modus
                if(!data.active_question){
                  if(lastMode!=='idle'){
                    setVisible("#dgptm_resultsGrid", false);
                    setVisible("#dgptm_beamerQuestionArea", false);
                    var logo = data.active_poll.logo_url || '';
                    if(logo && logo!==lastLogo){ $("#dgptm_beamerLogo").attr("src",logo); lastLogo=logo; }
                    setVisible("#dgptm_beamerLogo", !!logo);
                    $("#dgptm_beamerFillerText").text("<?php echo esc_js($no_poll_text); ?>");
                    setVisible("#dgptm_beamerFillerText", true);
                  }
                  lastMode='idle'; questionActive=false; questionSeconds=0; schedule(2500); return;
                }

                if(lastMode!=='live'){
                  setVisible("#dgptm_resultsGrid", false);
                  setVisible("#dgptm_beamerFillerText", false);
                  setVisible("#dgptm_beamerLogo", false);
                  setVisible("#dgptm_beamerQuestionArea", true);
                  $("#dgptm_beamerChart").hide();
                  $("#dgptm_liveBar").show();
                }
                var q = data.active_question;
                if((q.question||'')!==lastTitle){ $("#dgptm_questionTitle").text(q.question||""); lastTitle=q.question||''; }

                var tv = (data.active_results && data.active_results.total_votes) ? data.active_results.total_votes : 0;
                if(tv!==lastLiveCount || attendees!==lastAttendees){
                  liveBar(tv, attendees);
                  lastLiveCount=tv; lastAttendees=attendees;
                }

                lastMode='live';
                questionActive=true;
                schedule(1000);
              },"json").fail(function(){ schedule(4000); });
            }

            function renderChart(canvasId, labels, values, chartType){
              if(typeof Chart==="undefined") return;
              var ctx=document.getElementById(canvasId);
              if(!ctx) return;
              if(ctx._chart){ ctx._chart.destroy(); }
              var colors=[
                "rgba(255,99,132,0.65)","rgba(54,162,235,0.65)","rgba(255,206,86,0.65)",
                "rgba(75,192,192,0.65)","rgba(153,102,255,0.65)","rgba(255,159,64,0.65)",
                "rgba(201,203,207,0.65)","rgba(99,255,132,0.65)"
              ];
              var type = (chartType==='pie') ? 'pie' : 'bar';
              var data, options;

              if(type==='pie'){
                data = {labels:labels, datasets:[{data:values, backgroundColor:colors}]};
                options = {responsive:true, animation:false, plugins:{legend:{display:true}}};
              } else {
                data = {labels:labels, datasets:[{label:"Stimmen",data:values,backgroundColor:colors,borderWidth:1}]};
                options = {responsive:true, animation:false, scales:{y:{beginAtZero:true,ticks:{stepSize:1}}}};
              }

              ctx._chart=new Chart(ctx,{type:type,data:data,options:options});
            }

            updateClock();
            loadState();
          });
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}

if (!shortcode_exists('beamer_view')) {
    add_shortcode('beamer_view','dgptm_beamer_view');
}
