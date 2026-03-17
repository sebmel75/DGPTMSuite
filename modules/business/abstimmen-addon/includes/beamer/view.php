<?php
// File: includes/beamer/view.php
// Clean White Beamer — v4.2.0
// Komplett AJAX-basiert, keinerlei Benutzerinteraktion noetig.

if (!defined('ABSPATH')) exit;

if (!function_exists('dgptm_beamer_view')) {
    function dgptm_beamer_view() {
        wp_enqueue_style( 'dgptm-abstimmen-frontend' );
        wp_enqueue_script( 'dgptm-abstimmen-frontend' );

        if (!function_exists('dgptm_is_manager') || !dgptm_is_manager()) return '<p>Keine Berechtigung.</p>';

        $no_poll_text = get_option('dgptm_no_poll_text', 'Bitte warten ...');
        ob_start(); ?>

        <style>
          /* Hide WP admin bar, header, footer for true fullscreen */
          #wpadminbar,header,.site-header,.elementor-location-header,
          footer,.site-footer,.elementor-location-footer,
          nav.navbar,.wp-site-blocks>header{display:none!important;}
          html{margin-top:0!important;}
          html,body{margin:0;padding:0;height:100%;overflow:hidden;background:#fff;cursor:none;}
          *{box-sizing:border-box;}
          #dgptm_beamer{
            position:fixed;top:0;left:0;width:100vw;height:100vh;overflow:hidden;
            background:#fff;color:#1e293b;z-index:99999;
            font-family:'Segoe UI',system-ui,-apple-system,sans-serif;
          }
          /* Accent bar */
          .dgptm-b-accent{position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,#2d6cdf,#06b6d4);z-index:10;}
          /* Clock */
          .dgptm-b-clock{position:absolute;top:16px;left:20px;font-size:18px;font-weight:700;color:#64748b;z-index:10;}
          .dgptm-b-clock.timer-active{font-size:32px;color:#dc2626;font-weight:800;}
          .dgptm-b-clock.timer-urgent{animation:bpulse .7s ease-in-out infinite alternate;}
          @keyframes bpulse{from{opacity:1}to{opacity:.35}}
          /* Poll name */
          .dgptm-b-pollname{position:absolute;top:16px;right:20px;font-size:14px;color:#94a3b8;z-index:10;}
          /* Content */
          .dgptm-b-content{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:90%;max-width:1200px;text-align:center;}
          /* QR */
          .dgptm-b-qr{position:absolute;bottom:20px;right:20px;background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:10px;box-shadow:0 2px 8px rgba(0,0,0,.08);display:none;z-index:10;}
          .dgptm-b-qr canvas{width:150px!important;height:150px!important;}
          /* Idle */
          .b-idle-logo{max-width:30%;max-height:30vh;opacity:.6;margin-bottom:16px;}
          .b-idle-text{font-size:22px;color:#94a3b8;}
          /* Voting active */
          .b-vote-label{font-size:12px;text-transform:uppercase;letter-spacing:3px;color:#94a3b8;margin-bottom:10px;}
          .b-vote-question{font-size:clamp(26px,4vw,42px);font-weight:700;line-height:1.25;margin-bottom:20px;color:#0f172a;}
          .b-vote-options{display:flex;gap:14px;justify-content:center;flex-wrap:wrap;margin-bottom:24px;}
          .b-vote-opt{display:flex;align-items:center;gap:8px;padding:8px 16px;border:2px solid #e2e8f0;border-radius:12px;background:#fafbfd;font-size:clamp(14px,2vw,18px);font-weight:600;color:#334155;}
          .b-vote-opt img{width:48px;height:48px;border-radius:8px;object-fit:cover;}
          .b-vote-progress{width:55%;margin:0 auto;}
          .b-vote-stats{display:flex;justify-content:space-between;font-size:13px;color:#94a3b8;margin-bottom:6px;}
          .b-progress-bar{height:10px;background:#f1f5f9;border-radius:5px;overflow:hidden;}
          .b-progress-fill{height:100%;border-radius:5px;transition:width .5s;background:linear-gradient(90deg,#3b82f6,#06b6d4);}
          /* Waiting */
          .b-waiting{font-size:22px;color:#64748b;}
          .b-waiting-dot{display:inline-block;width:10px;height:10px;border-radius:50%;background:#f59e0b;margin-left:8px;vertical-align:middle;animation:bpulse 1.2s ease-in-out infinite alternate;}
          /* Result cards */
          .b-q-title{font-size:clamp(18px,3vw,28px);font-weight:600;margin-bottom:24px;color:#334155;}
          .b-cards{display:flex;gap:16px;justify-content:center;flex-wrap:wrap;}
          .b-card{flex:1 1 160px;max-width:260px;border-radius:16px;padding:24px 16px;text-align:center;border:2px solid #e2e8f0;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,.04);animation:bcardin .5s ease-out both;}
          @keyframes bcardin{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
          .b-card-img{width:80px;height:80px;border-radius:50%;object-fit:cover;margin:0 auto 10px;display:block;border:3px solid #e2e8f0;}
          .b-card-pct{font-size:clamp(36px,5vw,56px);font-weight:800;line-height:1;}
          .b-card-label{font-size:16px;font-weight:600;margin-top:8px;color:#334155;}
          .b-card-count{font-size:13px;color:#94a3b8;margin-top:4px;}
          /* Horizontal bars */
          .b-hbars{display:flex;flex-direction:column;gap:14px;max-width:700px;margin:0 auto;text-align:left;}
          .b-hbar-row .b-hbar-head{display:flex;justify-content:space-between;font-size:14px;margin-bottom:4px;}
          .b-hbar-row .b-hbar-head .b-hbar-name{font-weight:600;color:#334155;display:flex;align-items:center;gap:8px;}
          .b-hbar-row .b-hbar-head .b-hbar-name img{width:28px;height:28px;border-radius:50%;object-fit:cover;}
          .b-hbar-row .b-hbar-val{font-weight:700;}
          .b-hbar-track{height:32px;background:#f1f5f9;border-radius:8px;overflow:hidden;}
          .b-hbar-fill{height:100%;border-radius:8px;display:flex;align-items:center;padding-left:12px;font-size:14px;font-weight:700;color:#fff;transition:width .6s ease;}
          /* Vertical bars (columns) */
          .b-vbars{display:flex;gap:20px;justify-content:center;align-items:flex-end;height:260px;}
          .b-vbar{display:flex;flex-direction:column;align-items:center;gap:6px;flex:1;max-width:120px;}
          .b-vbar-pct{font-size:18px;font-weight:800;}
          .b-vbar-col{width:100%;border-radius:8px 8px 0 0;transition:height .6s ease;min-height:4px;}
          .b-vbar-label{font-size:12px;font-weight:600;color:#64748b;text-align:center;}
          .b-vbar-img{width:40px;height:40px;border-radius:50%;object-fit:cover;}
          /* Pie chart */
          .b-pie-wrap{display:flex;align-items:center;justify-content:center;gap:40px;flex-wrap:wrap;}
          .b-pie-canvas{width:280px;height:280px;}
          .b-pie-legend{display:flex;flex-direction:column;gap:10px;text-align:left;}
          .b-pie-legend-item{display:flex;align-items:center;gap:8px;font-size:15px;}
          .b-pie-legend-dot{width:16px;height:16px;border-radius:4px;flex-shrink:0;}
          .b-pie-legend-label{font-weight:600;color:#334155;}
          .b-pie-legend-val{color:#64748b;font-size:13px;}
          /* Result text */
          .b-result-text{margin-top:20px;font-size:17px;font-weight:600;}
          .b-result-passed{color:#16a34a;}
          .b-result-failed{color:#dc2626;}
          .b-result-runoff{color:#d97706;}
          /* All results grid */
          .b-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(360px,1fr));gap:28px;text-align:center;}
          .b-grid .b-q-title{font-size:16px;margin-bottom:14px;}
          .b-grid .b-card{padding:14px 10px;}
          .b-grid .b-card-pct{font-size:30px;}
          .b-grid .b-card-label{font-size:13px;}
          .b-grid .b-result-text{font-size:14px;}
          /* Custom content */
          .b-custom{font-size:20px;line-height:1.6;color:#334155;max-width:800px;margin:0 auto;}
          .b-custom img{max-width:100%;height:auto;border-radius:12px;}
        </style>

        <div id="dgptm_beamer">
          <div class="dgptm-b-accent"></div>
          <div id="bClock" class="dgptm-b-clock">--:--</div>
          <div id="bPollName" class="dgptm-b-pollname"></div>
          <div id="bContent" class="dgptm-b-content"></div>
          <div id="bQR" class="dgptm-b-qr"></div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
        <script>
        (function(){
          var AX='<?php echo esc_js(admin_url("admin-ajax.php")); ?>';
          var HOME='<?php echo esc_js(home_url("/")); ?>';
          var IDLE_TEXT=<?php echo wp_json_encode($no_poll_text); ?>;
          var COLORS=['#22c55e','#ef4444','#f59e0b','#3b82f6','#8b5cf6','#f97316','#ec4899','#14b8a6'];
          var poll=1500, clockTick=null, localR=null, lastR=null, qrDone=false;

          function esc(s){var d=document.createElement('div');d.textContent=s;return d.innerHTML;}
          function hexRgba(h,a){var r=parseInt(h.slice(1,3),16),g=parseInt(h.slice(3,5),16),b=parseInt(h.slice(5,7),16);return'rgba('+r+','+g+','+b+','+a+')';}

          // Auto-fullscreen on first interaction (browsers require user gesture)
          var fsRequested=false;
          document.addEventListener('click',function(){
            if(fsRequested)return;fsRequested=true;
            var el=document.documentElement;
            if(el.requestFullscreen)el.requestFullscreen();
            else if(el.webkitRequestFullscreen)el.webkitRequestFullscreen();
            else if(el.msRequestFullscreen)el.msRequestFullscreen();
          },{once:true});
          // Also try on first load (may work in kiosk/app mode)
          try{var el=document.documentElement;if(el.requestFullscreen)el.requestFullscreen();}catch(e){}

          // Prevent screen sleep via Wake Lock API
          if('wakeLock' in navigator){
            navigator.wakeLock.request('screen').catch(function(){});
            document.addEventListener('visibilitychange',function(){
              if(document.visibilityState==='visible')navigator.wakeLock.request('screen').catch(function(){});
            });
          }

          // Clock
          function startClock(){
            if(clockTick)clearInterval(clockTick);
            clockTick=setInterval(function(){
              var el=document.getElementById('bClock');if(!el)return;
              if(localR!==null){
                localR--;if(localR<0)localR=0;
                var m=Math.floor(localR/60),s=localR%60;
                el.textContent=m+':'+String(s).padStart(2,'0');
                el.className='dgptm-b-clock timer-active'+(localR<10?' timer-urgent':'');
              }else{
                var n=new Date();
                el.textContent=String(n.getHours()).padStart(2,'0')+':'+String(n.getMinutes()).padStart(2,'0');
                el.className='dgptm-b-clock';
              }
            },1000);
          }
          startClock();

          // QR — uses <img> via qrserver.com API (reliable, always black on white)
          function showQR(pid){
            var el=document.getElementById('bQR');if(!el)return;
            if(!qrDone){
              var url=HOME+'?dgptm_member=1&poll_id='+pid;
              var imgSrc='https://api.qrserver.com/v1/create-qr-code/?size=300x300&color=000000&bgcolor=ffffff&data='+encodeURIComponent(url);
              el.innerHTML='<img src="'+imgSrc+'" alt="QR" style="width:150px;height:150px;display:block;">';
              qrDone=true;
            }
            el.style.display='block';
          }
          function hideQR(){var el=document.getElementById('bQR');if(el)el.style.display='none';}

          // === Render functions ===
          function renderIdle(d){
            localR=null;
            var h='';
            if(d.active_poll&&d.active_poll.logo_url)h+='<img src="'+esc(d.active_poll.logo_url)+'" class="b-idle-logo" alt="">';
            h+='<div class="b-idle-text">'+IDLE_TEXT+'</div>';
            document.getElementById('bContent').innerHTML=h;
            hideQR();
          }

          function renderCustomContent(d){
            localR=null;
            var html=d.active_poll.beamer_content||'';
            document.getElementById('bContent').innerHTML='<div class="b-custom">'+html+'</div>';
            if(d.beamer_state&&d.beamer_state.qr_visible&&d.active_poll)showQR(d.active_poll.id);else hideQR();
          }

          function renderVoting(d){
            var q=d.active_question,res=d.active_results||{};
            var total=res.total_votes||0,att=d.attendees||0,pct=att>0?Math.round(total/att*100):0;
            if(d.timer&&d.timer.remaining_seconds!==null&&d.timer.time_limit>0){
              if(lastR!==d.timer.remaining_seconds){localR=Math.max(0,d.timer.remaining_seconds);lastR=d.timer.remaining_seconds;}
            }else localR=null;
            var h='<div class="b-vote-label">Abstimmung</div>';
            h+='<div class="b-vote-question">'+esc(q.question)+'</div>';
            // Show choices with images if available
            var choices=q.choices;if(typeof choices==='string'){try{choices=JSON.parse(choices)}catch(e){choices=[]}}
            if(Array.isArray(choices)&&choices.length>0){
              var images=q.choice_images;if(typeof images==='string'){try{images=JSON.parse(images)}catch(e){images=null}}
              if(!Array.isArray(images))images=null;
              h+='<div class="b-vote-options">';
              for(var i=0;i<choices.length;i++){
                h+='<div class="b-vote-opt" style="border-color:'+COLORS[i%COLORS.length]+'">';
                if(images&&images[i])h+='<img src="'+esc(images[i])+'" alt="">';
                h+=esc(choices[i])+'</div>';
              }
              h+='</div>';
            }
            h+='<div class="b-vote-progress"><div class="b-vote-stats"><span>'+total+' von '+att+' Stimmen</span><span>'+pct+'%</span></div>';
            h+='<div class="b-progress-bar"><div class="b-progress-fill" style="width:'+pct+'%"></div></div></div>';
            document.getElementById('bContent').innerHTML=h;
            if(d.active_poll)showQR(d.active_poll.id);
          }

          function renderWaiting(d){
            localR=null;
            document.getElementById('bContent').innerHTML='<div class="b-waiting">Abstimmung beendet — Ergebnis wird ausgewertet <span class="b-waiting-dot"></span></div>';
            hideQR();
          }

          // === Result renderers ===
          function buildResults(q){
            var choices=q.choices;if(typeof choices==='string'){try{choices=JSON.parse(choices)}catch(e){choices=[]}}
            if(!Array.isArray(choices))choices=[];
            var votes=q.votes||[],total=q.total_votes||0;
            var images=q.choice_images;if(typeof images==='string'){try{images=JSON.parse(images)}catch(e){images=null}}
            if(!Array.isArray(images))images=null;
            var dt=q.display_type||q.chart_type||'cards';

            var h='<div class="b-q-title">'+esc(q.question)+'</div>';

            var winners=(q.majority&&q.majority.winners)?q.majority.winners:[];

            if(dt==='horizontal_bars'){
              h+=buildHBars(choices,votes,total,images,winners);
            }else if(dt==='vertical_bars'){
              h+=buildVBars(choices,votes,total,images,winners);
            }else if(dt==='pie'){
              h+=buildPie(choices,votes,total,images);
            }else{
              h+=buildCards(choices,votes,total,images,winners);
            }

            if(q.majority){
              var m=q.majority;
              var icon,cls;
              if(m.runoff){icon='\u26A0';cls='b-result-runoff';}
              else if(m.passed){icon='\u2713';cls='b-result-passed';}
              else{icon='\u2717';cls='b-result-failed';}
              h+='<div class="b-result-text '+cls+'">'+icon+' '+esc(m.label)+' \u00b7 '+total+' Stimmen';
              if(m.quorum>0)h+=' \u00b7 Quorum '+(m.quorum_met?'erreicht':'nicht erreicht');
              h+='</div>';
            }
            return h;
          }

          function buildCards(choices,votes,total,images,winners){
            winners=winners||[];
            // Find index with most votes
            var maxIdx=0;for(var j=1;j<votes.length;j++){if((votes[j]||0)>(votes[maxIdx]||0))maxIdx=j;}
            var hasResult=winners.length>0||total>0;
            var h='<div class="b-cards">';
            for(var i=0;i<choices.length;i++){
              var cnt=votes[i]||0,pct=total>0?Math.round(cnt/total*100):0;
              var delay=(i*.1).toFixed(1);
              var isWinner=winners.length>0?winners.indexOf(i)>-1:(i===maxIdx&&total>0);
              // Green for winner(s), red for losers
              var c=hasResult?(isWinner?'#16a34a':'#dc2626'):COLORS[i%COLORS.length];
              var winStyle=isWinner?' box-shadow:0 0 0 3px #16a34a,0 4px 12px rgba(0,0,0,.15);':'';
              h+='<div class="b-card" style="border-color:'+c+';animation-delay:'+delay+'s;'+winStyle+'">';
              if(isWinner&&winners.length>0)h+='<div style="font-size:11px;font-weight:700;color:#16a34a;margin-bottom:4px;">\u2713 GEWAEHLT</div>';
              if(images&&images[i])h+='<img src="'+esc(images[i])+'" class="b-card-img" alt="" style="border-color:'+c+'">';
              h+='<div class="b-card-pct" style="color:'+c+'">'+pct+'%</div>';
              h+='<div class="b-card-label">'+esc(choices[i])+'</div>';
              h+='<div class="b-card-count">'+cnt+' Stimmen</div></div>';
            }
            return h+'</div>';
          }

          function buildHBars(choices,votes,total,images,winners){
            winners=winners||[];
            var maxIdx=0;for(var j=1;j<votes.length;j++){if((votes[j]||0)>(votes[maxIdx]||0))maxIdx=j;}
            var hasResult=winners.length>0||total>0;
            var h='<div class="b-hbars">';
            for(var i=0;i<choices.length;i++){
              var cnt=votes[i]||0,pct=total>0?Math.round(cnt/total*100):0;
              var isWinner=winners.length>0?winners.indexOf(i)>-1:(i===maxIdx&&total>0);
              var c=hasResult?(isWinner?'#16a34a':'#dc2626'):COLORS[i%COLORS.length];
              h+='<div class="b-hbar-row"><div class="b-hbar-head"><span class="b-hbar-name">';
              if(images&&images[i])h+='<img src="'+esc(images[i])+'" alt="">';
              h+=(isWinner&&winners.length>0?'\u2713 ':'')+esc(choices[i])+'</span><span class="b-hbar-val" style="color:'+c+'">'+cnt+' ('+pct+'%)</span></div>';
              h+='<div class="b-hbar-track"><div class="b-hbar-fill" style="width:'+pct+'%;background:'+c+'">'+( pct>8?pct+'%':'')+'</div></div></div>';
            }
            return h+'</div>';
          }

          function buildVBars(choices,votes,total,images,winners){
            winners=winners||[];
            var maxIdx=0;for(var j=1;j<votes.length;j++){if((votes[j]||0)>(votes[maxIdx]||0))maxIdx=j;}
            var hasResult=winners.length>0||total>0;
            var maxV=Math.max.apply(null,votes.concat([1]));
            var h='<div class="b-vbars">';
            for(var i=0;i<choices.length;i++){
              var cnt=votes[i]||0,pct=total>0?Math.round(cnt/total*100):0;
              var isWinner=winners.length>0?winners.indexOf(i)>-1:(i===maxIdx&&total>0);
              var c=hasResult?(isWinner?'#16a34a':'#dc2626'):COLORS[i%COLORS.length];
              var barH=Math.round((cnt/maxV)*200);
              h+='<div class="b-vbar">';
              h+='<div class="b-vbar-pct" style="color:'+c+'">'+pct+'%</div>';
              h+='<div class="b-vbar-col" style="height:'+barH+'px;background:'+c+'"></div>';
              if(images&&images[i])h+='<img src="'+esc(images[i])+'" class="b-vbar-img" alt="">';
              h+='<div class="b-vbar-label">'+esc(choices[i])+'</div></div>';
            }
            return h+'</div>';
          }

          function buildPie(choices,votes,total,images){
            var uid='pie_'+Math.random().toString(36).substr(2,6);
            var h='<div class="b-pie-wrap">';
            h+='<canvas id="'+uid+'" class="b-pie-canvas" width="280" height="280"></canvas>';
            h+='<div class="b-pie-legend">';
            for(var i=0;i<choices.length;i++){
              var cnt=votes[i]||0,pct=total>0?Math.round(cnt/total*100):0,c=COLORS[i%COLORS.length];
              h+='<div class="b-pie-legend-item">';
              h+='<span class="b-pie-legend-dot" style="background:'+c+'"></span>';
              if(images&&images[i])h+='<img src="'+esc(images[i])+'" style="width:24px;height:24px;border-radius:50%;object-fit:cover;" alt="">';
              h+='<span class="b-pie-legend-label">'+esc(choices[i])+'</span>';
              h+='<span class="b-pie-legend-val">'+cnt+' ('+pct+'%)</span>';
              h+='</div>';
            }
            h+='</div></div>';
            // Draw pie after DOM update
            setTimeout(function(){
              var cv=document.getElementById(uid);if(!cv||!cv.getContext)return;
              var ctx=cv.getContext('2d'),cx=140,cy=140,r=120;
              var startAngle=-Math.PI/2;
              for(var i=0;i<choices.length;i++){
                var cnt=votes[i]||0,slice=total>0?(cnt/total)*Math.PI*2:0;
                if(slice<0.001&&total>0)slice=0.005; // minimal slice visibility
                ctx.beginPath();ctx.moveTo(cx,cy);
                ctx.arc(cx,cy,r,startAngle,startAngle+slice);
                ctx.closePath();ctx.fillStyle=COLORS[i%COLORS.length];ctx.fill();
                startAngle+=slice;
              }
              // Center hole (donut)
              ctx.beginPath();ctx.arc(cx,cy,r*0.55,0,Math.PI*2);ctx.fillStyle='#fff';ctx.fill();
              // Center text
              ctx.fillStyle='#1e293b';ctx.font='bold 32px system-ui';ctx.textAlign='center';ctx.textBaseline='middle';
              ctx.fillText(total,cx,cy-8);
              ctx.font='12px system-ui';ctx.fillStyle='#94a3b8';ctx.fillText('Stimmen',cx,cy+14);
            },50);
            return h;
          }

          // === Main ===
          function render(d){
            document.getElementById('bPollName').textContent=d.active_poll?d.active_poll.name:'';
            var st=d.beamer_state||{};

            // Custom content mode
            if(d.active_poll&&d.active_poll.beamer_content_active&&d.active_poll.beamer_content){
              renderCustomContent(d);poll=1500;return;
            }
            if(st.mode==='results_all'&&d.state_questions){
              localR=null;
              var h='<div class="b-grid">';
              for(var i=0;i<d.state_questions.length;i++)h+='<div>'+buildResults(d.state_questions[i])+'</div>';
              h+='</div>';
              document.getElementById('bContent').innerHTML=h;hideQR();poll=1500;
            }else if(st.mode==='results_one'&&d.state_question){
              localR=null;
              if(!d.state_question.released){renderIdle(d);return;}
              document.getElementById('bContent').innerHTML=buildResults(d.state_question);hideQR();poll=1500;
            }else if(d.active_question&&d.active_question.status==='active'){
              renderVoting(d);poll=1000;
            }else if(d.active_question&&d.active_question.status==='stopped'){
              if(d.active_question.results_released){
                localR=null;document.getElementById('bContent').innerHTML=buildResults(d.active_question);hideQR();poll=1500;
              }else{renderWaiting(d);poll=1500;}
            }else{renderIdle(d);poll=1500;}

            if(st.qr_visible&&d.active_poll)showQR(d.active_poll.id);
          }

          // === AJAX loop ===
          function fetch(){
            var x=new XMLHttpRequest();
            x.open('POST',AX,true);
            x.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
            x.onreadystatechange=function(){
              if(x.readyState!==4)return;
              if(x.status===200){try{var r=JSON.parse(x.responseText);if(r&&r.success&&r.data)render(r.data);}catch(e){}}
              else poll=1500;
              setTimeout(fetch,poll);
            };
            x.send('action=dgptm_get_beamer_payload');
          }
          fetch();
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}

if (!shortcode_exists('beamer_view')) {
    add_action('init', function(){ add_shortcode('beamer_view','dgptm_beamer_view'); }, 5);
}
