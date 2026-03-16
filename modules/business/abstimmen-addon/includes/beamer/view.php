<?php
// File: includes/beamer/view.php
// Corporate Dark Beamer — Redesign v4.1.0

if (!defined('ABSPATH')) exit;

/**
 * Shortcode: [beamer_view]
 * Corporate Dark Design mit Ergebnis-Karten, Timer-Countdown, Mehrheitsanzeige.
 */
if (!function_exists('dgptm_beamer_view')) {
    function dgptm_beamer_view() {
        wp_enqueue_style( 'dgptm-abstimmen-frontend' );
        wp_enqueue_script( 'dgptm-abstimmen-frontend' );

        if (!function_exists('dgptm_is_manager') || !dgptm_is_manager()) return '<p>Keine Berechtigung.</p>';

        $no_poll_text = get_option('dgptm_no_poll_text', 'Bitte warten ...');
        ob_start(); ?>

        <style>
          /* === Corporate Dark Beamer === */
          html, body { margin:0; padding:0; height:100%; overflow:hidden; }
          #dgptm_beamer {
            width:100%; height:100vh; position:relative; overflow:hidden;
            background:#111827; color:#fff;
            font-family:'Segoe UI',system-ui,-apple-system,sans-serif;
          }
          .dgptm-beamer-accent {
            position:absolute; top:0; left:0; right:0; height:4px;
            background:linear-gradient(90deg,#2d6cdf,#06b6d4);
            z-index:10;
          }
          /* Clock / Timer */
          .dgptm-beamer-clock {
            position:absolute; top:18px; left:20px;
            font-size:18px; font-weight:700; letter-spacing:1px;
            color:rgba(255,255,255,0.9); z-index:10;
          }
          .dgptm-beamer-clock.dgptm-timer-active {
            font-size:28px; color:#f87171; font-weight:800;
          }
          .dgptm-beamer-clock.dgptm-timer-urgent {
            animation: dgptm-beamer-pulse 0.7s ease-in-out infinite alternate;
          }
          @keyframes dgptm-beamer-pulse { from{opacity:1;} to{opacity:0.4;} }

          /* Poll name */
          .dgptm-beamer-poll-name {
            position:absolute; top:18px; right:20px;
            font-size:14px; color:rgba(255,255,255,0.5); z-index:10;
          }
          /* Main content */
          .dgptm-beamer-content {
            position:absolute; top:50%; left:50%; transform:translate(-50%,-50%);
            width:90%; max-width:1200px; text-align:center;
          }
          /* QR code */
          .dgptm-beamer-qr {
            position:absolute; bottom:20px; right:20px; z-index:10;
            background:#fff; border-radius:8px; padding:6px;
            box-shadow:0 2px 12px rgba(0,0,0,0.4);
            display:none;
          }
          .dgptm-beamer-qr canvas { width:64px!important; height:64px!important; }

          /* === Idle State === */
          .dgptm-idle-logo { max-width:40%; max-height:40vh; opacity:0.7; margin-bottom:20px; }
          .dgptm-idle-text { font-size:20px; opacity:0.4; }

          /* === Active Voting === */
          .dgptm-voting-label {
            font-size:12px; text-transform:uppercase; letter-spacing:3px;
            color:rgba(255,255,255,0.4); margin-bottom:12px;
          }
          .dgptm-voting-question {
            font-size:clamp(24px,4vw,38px); font-weight:700; line-height:1.3;
            margin-bottom:24px;
          }
          .dgptm-voting-progress { width:60%; margin:0 auto; }
          .dgptm-voting-stats {
            display:flex; justify-content:space-between;
            font-size:13px; color:rgba(255,255,255,0.5); margin-bottom:6px;
          }
          .dgptm-progress-bar {
            height:8px; background:rgba(255,255,255,0.1); border-radius:4px; overflow:hidden;
          }
          .dgptm-progress-fill {
            height:100%; border-radius:4px; transition:width 0.5s ease;
            background:linear-gradient(90deg,#3b82f6,#06b6d4);
          }

          /* === Waiting for Release === */
          .dgptm-waiting-text {
            font-size:22px; color:rgba(255,255,255,0.6);
          }
          .dgptm-waiting-dot {
            display:inline-block; width:10px; height:10px; border-radius:50%;
            background:#fbbf24; margin-left:8px; vertical-align:middle;
            animation: dgptm-beamer-pulse 1.2s ease-in-out infinite alternate;
          }

          /* === Result Cards === */
          .dgptm-beamer-question-title {
            font-size:clamp(18px,3vw,26px); font-weight:600;
            margin-bottom:24px; color:rgba(255,255,255,0.85);
          }
          .dgptm-beamer-cards {
            display:flex; gap:16px; justify-content:center; flex-wrap:wrap;
          }
          .dgptm-beamer-result-card {
            flex:1 1 160px; max-width:240px;
            border:1px solid rgba(255,255,255,0.15);
            border-radius:14px; padding:20px 16px; text-align:center;
            transition:transform 0.3s ease, opacity 0.3s ease;
            animation: dgptm-card-in 0.5s ease-out both;
          }
          @keyframes dgptm-card-in {
            from { opacity:0; transform:translateY(20px); }
            to { opacity:1; transform:translateY(0); }
          }
          .dgptm-card-pct {
            font-size:clamp(32px,5vw,52px); font-weight:800; line-height:1;
          }
          .dgptm-card-label {
            font-size:15px; font-weight:600; margin-top:8px;
            color:rgba(255,255,255,0.9);
          }
          .dgptm-card-count {
            font-size:12px; color:rgba(255,255,255,0.4); margin-top:4px;
          }
          /* Result text */
          .dgptm-beamer-result-text {
            margin-top:20px; font-size:16px; font-weight:600;
          }
          .dgptm-result-passed { color:#4ade80; }
          .dgptm-result-failed { color:#f87171; }

          /* === All Results Grid === */
          .dgptm-results-grid {
            display:grid; grid-template-columns:repeat(auto-fit,minmax(400px,1fr));
            gap:24px; text-align:center;
          }
          .dgptm-results-grid .dgptm-beamer-question-title { font-size:16px; margin-bottom:12px; }
          .dgptm-results-grid .dgptm-beamer-result-card { padding:12px 10px; }
          .dgptm-results-grid .dgptm-card-pct { font-size:28px; }
          .dgptm-results-grid .dgptm-card-label { font-size:12px; }
          .dgptm-results-grid .dgptm-beamer-result-text { font-size:13px; }
        </style>

        <div id="dgptm_beamer">
          <div class="dgptm-beamer-accent"></div>
          <div id="dgptm_beamerClock" class="dgptm-beamer-clock">--:--</div>
          <div id="dgptm_beamerPollName" class="dgptm-beamer-poll-name"></div>
          <div id="dgptm_beamerContent" class="dgptm-beamer-content"></div>
          <div id="dgptm_beamerQR" class="dgptm-beamer-qr"></div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
        <script>
        (function(){
          var ajaxUrl = '<?php echo esc_js(admin_url("admin-ajax.php")); ?>';
          var homeUrl = '<?php echo esc_js(home_url("/")); ?>';
          var noPollText = <?php echo wp_json_encode($no_poll_text); ?>;
          var COLORS = ['#4ade80','#f87171','#fbbf24','#60a5fa','#a78bfa','#fb923c','#e879f9','#34d399'];
          var pollInterval = 3000;
          var clockTimer = null;
          var lastRemainingFromServer = null;
          var localRemaining = null;

          function esc(s){ var d=document.createElement('div'); d.textContent=s; return d.innerHTML; }
          function hexToRgba(hex, a){
            var r=parseInt(hex.slice(1,3),16), g=parseInt(hex.slice(3,5),16), b=parseInt(hex.slice(5,7),16);
            return 'rgba('+r+','+g+','+b+','+a+')';
          }

          // === Clock ===
          function startClockTick(){
            if(clockTimer) clearInterval(clockTimer);
            clockTimer = setInterval(function(){
              var el = document.getElementById('dgptm_beamerClock');
              if(!el) return;
              if(localRemaining !== null){
                localRemaining--;
                if(localRemaining < 0) localRemaining = 0;
                var m = Math.floor(localRemaining/60), s = localRemaining%60;
                el.textContent = m+':'+String(s).padStart(2,'0');
                el.classList.add('dgptm-timer-active');
                el.classList.toggle('dgptm-timer-urgent', localRemaining < 10);
              } else {
                var now = new Date();
                el.textContent = String(now.getHours()).padStart(2,'0')+':'+String(now.getMinutes()).padStart(2,'0');
                el.classList.remove('dgptm-timer-active','dgptm-timer-urgent');
              }
            }, 1000);
          }
          startClockTick();

          // === QR ===
          var qrDrawn = false;
          function drawQR(pollId){
            var el = document.getElementById('dgptm_beamerQR');
            if(!el || qrDrawn) { if(el) el.style.display='block'; return; }
            var url = homeUrl + '?dgptm_member=1&poll_id=' + pollId;
            el.innerHTML = '';
            var canvas = document.createElement('canvas');
            el.appendChild(canvas);
            if(typeof QRCode !== 'undefined' && QRCode.toCanvas){
              QRCode.toCanvas(canvas, url, {width:64, margin:1, errorCorrectionLevel:'M'});
            }
            el.style.display = 'block';
            qrDrawn = true;
          }
          function hideQR(){ var el=document.getElementById('dgptm_beamerQR'); if(el) el.style.display='none'; }

          // === Render States ===
          function renderIdle(data){
            localRemaining = null;
            var html = '';
            if(data.active_poll && data.active_poll.logo_url){
              html += '<img src="'+esc(data.active_poll.logo_url)+'" class="dgptm-idle-logo" alt="Logo">';
            }
            html += '<div class="dgptm-idle-text">'+esc(noPollText)+'</div>';
            document.getElementById('dgptm_beamerContent').innerHTML = html;
            hideQR();
          }

          function renderActiveVoting(data){
            var q = data.active_question;
            var res = data.active_results || {};
            var total = res.total_votes || 0;
            var att = data.attendees || 0;
            var pct = att > 0 ? Math.round(total/att*100) : 0;

            // Timer sync
            if(data.timer && data.timer.remaining_seconds !== null && data.timer.time_limit > 0){
              if(lastRemainingFromServer !== data.timer.remaining_seconds){
                localRemaining = Math.max(0, data.timer.remaining_seconds);
                lastRemainingFromServer = data.timer.remaining_seconds;
              }
            } else {
              localRemaining = null;
            }

            var html = '<div class="dgptm-voting-label">Abstimmung</div>';
            html += '<div class="dgptm-voting-question">'+esc(q.question)+'</div>';
            html += '<div class="dgptm-voting-progress">';
            html += '<div class="dgptm-voting-stats"><span>'+total+' von '+att+' Stimmen</span><span>'+pct+'%</span></div>';
            html += '<div class="dgptm-progress-bar"><div class="dgptm-progress-fill" style="width:'+pct+'%"></div></div>';
            html += '</div>';
            document.getElementById('dgptm_beamerContent').innerHTML = html;

            if(data.active_poll) drawQR(data.active_poll.id);
          }

          function renderWaitingForRelease(data){
            localRemaining = null;
            var html = '<div class="dgptm-waiting-text">Abstimmung beendet — Ergebnis wird ausgewertet <span class="dgptm-waiting-dot"></span></div>';
            document.getElementById('dgptm_beamerContent').innerHTML = html;
            hideQR();
          }

          function buildResultCards(qData){
            var choices = qData.choices;
            if(typeof choices === 'string'){ try{ choices = JSON.parse(choices); }catch(e){ choices=[]; } }
            if(!Array.isArray(choices)) choices = [];
            var votes = qData.votes || [];
            var totalVotes = qData.total_votes || 0;

            var html = '<div class="dgptm-beamer-question-title">'+esc(qData.question)+'</div>';
            html += '<div class="dgptm-beamer-cards">';
            for(var i=0; i<choices.length; i++){
              var count = votes[i] || 0;
              var pct = totalVotes > 0 ? Math.round(count/totalVotes*100) : 0;
              var color = COLORS[i % COLORS.length];
              var delay = (i * 0.1).toFixed(1);
              html += '<div class="dgptm-beamer-result-card" style="border-color:'+color+';background:'+hexToRgba(color,0.12)+';animation-delay:'+delay+'s">';
              html += '<div class="dgptm-card-pct" style="color:'+color+'">'+pct+'%</div>';
              html += '<div class="dgptm-card-label">'+esc(choices[i])+'</div>';
              html += '<div class="dgptm-card-count">'+count+' Stimmen</div>';
              html += '</div>';
            }
            html += '</div>';

            if(qData.majority){
              var m = qData.majority;
              var icon = m.passed ? '\u2713' : '\u2717';
              var cls = m.passed ? 'dgptm-result-passed' : 'dgptm-result-failed';
              html += '<div class="dgptm-beamer-result-text '+cls+'">'+icon+' '+esc(m.label);
              html += ' \u00b7 '+totalVotes+' Stimmen';
              if(m.quorum > 0){
                html += ' \u00b7 Quorum '+(m.quorum_met ? 'erreicht' : 'nicht erreicht');
              }
              html += '</div>';
            }
            return html;
          }

          function renderSingleResult(data){
            localRemaining = null;
            var q = data.state_question;
            if(!q || !q.released){
              renderIdle(data); return;
            }
            document.getElementById('dgptm_beamerContent').innerHTML = buildResultCards(q);
            hideQR();
          }

          function renderAllResults(data){
            localRemaining = null;
            var qs = data.state_questions;
            if(!qs || !qs.length){ renderIdle(data); return; }
            var html = '<div class="dgptm-results-grid">';
            for(var i=0; i<qs.length; i++){
              html += '<div>' + buildResultCards(qs[i]) + '</div>';
            }
            html += '</div>';
            document.getElementById('dgptm_beamerContent').innerHTML = html;
            hideQR();
          }

          // === Main Render ===
          function renderBeamer(data){
            // Poll name
            var nameEl = document.getElementById('dgptm_beamerPollName');
            if(nameEl) nameEl.textContent = (data.active_poll ? data.active_poll.name : '');

            var st = data.beamer_state || {};

            if(st.mode === 'results_all' && data.state_questions){
              renderAllResults(data);
              pollInterval = 3000;
            } else if(st.mode === 'results_one' && data.state_question){
              renderSingleResult(data);
              pollInterval = 3000;
            } else if(data.active_question && data.active_question.status === 'active'){
              renderActiveVoting(data);
              pollInterval = 1000;
            } else if(data.active_question && data.active_question.status === 'stopped'){
              if(data.active_question.results_released){
                // Ergebnis schon freigegeben aber kein results_one Modus
                document.getElementById('dgptm_beamerContent').innerHTML = buildResultCards(data.active_question);
                localRemaining = null;
                pollInterval = 3000;
              } else {
                renderWaitingForRelease(data);
                pollInterval = 2000;
              }
            } else {
              renderIdle(data);
              pollInterval = 3000;
            }

            // QR visibility
            if(st.qr_visible && data.active_poll){
              drawQR(data.active_poll.id);
            } else if(!data.active_question || data.active_question.status !== 'active'){
              hideQR();
            }
          }

          // === Polling Loop ===
          function fetchPayload(){
            var xhr = new XMLHttpRequest();
            xhr.open('POST', ajaxUrl, true);
            xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
            xhr.onreadystatechange = function(){
              if(xhr.readyState !== 4) return;
              if(xhr.status === 200){
                try {
                  var resp = JSON.parse(xhr.responseText);
                  if(resp && resp.success && resp.data){
                    renderBeamer(resp.data);
                  }
                } catch(e){}
              } else {
                pollInterval = 5000;
              }
              setTimeout(fetchPayload, pollInterval);
            };
            xhr.send('action=dgptm_get_beamer_payload');
          }
          fetchPayload();

        })();
        </script>
        <?php
        return ob_get_clean();
    }
}

// Failsafe shortcode registration
if (!shortcode_exists('beamer_view')) {
    add_action('init', function(){
        add_shortcode('beamer_view', 'dgptm_beamer_view');
    }, 5);
}
