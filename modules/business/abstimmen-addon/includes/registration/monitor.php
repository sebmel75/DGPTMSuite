<?php
// File: includes/registration/monitor.php

if (!defined('ABSPATH')) exit;

/**
 * Shortcode: [dgptm_registration_monitor]
 * Vollständiger Registrierungsmonitor (Scanner + QR-Ausgabe + 30s Timeout + große Fehlermeldung)
 */
if (!function_exists('dgptm_registration_monitor_fn')) {
    function dgptm_registration_monitor_fn() {
        $enabled   = (int) get_option('dgptm_registration_enabled', 0);
        $targetPid = (int) get_option('dgptm_registration_poll_id', 0);

        ob_start(); ?>
        <style>
          .dgptm-regwrap{ max-width:980px; margin:20px auto; padding:16px; }
          .dgptm-regrow{ display:flex; gap:20px; flex-wrap:wrap; align-items:flex-start; }
          .dgptm-regbox{ flex:1 1 420px; border:1px solid #ddd; border-radius:12px; padding:16px; background:#fff; }
          .dgptm-big{ font-size: clamp(22px, 4vw, 34px); font-weight: 800; margin: 0 0 8px; text-align:center; }
          .dgptm-sub{ text-align:center; color:#555; margin-bottom:14px; }
          .dgptm-input{ width:100%; font-size:22px; padding:12px 14px; border:1px solid #bbb; border-radius:10px; }
          .dgptm-status{ margin-top:12px; padding:12px; border-radius:10px; text-align:center; font-weight:700; }
          .dgptm-status.ok{ background:#e7f6e9; color:#146c2e; border:1px solid #bfe5c6; }
          .dgptm-status.err{ background:#fde7e7; color:#8a1f1f; border:1px solid #f5c0c0; }
          .dgptm-qrbox{ display:none; flex-direction:column; align-items:center; justify-content:center; min-height:320px; border:1px dashed #ccc; border-radius:12px; background:#fafafa; padding:10px; }
          .dgptm-qrbox canvas{ width: 300px !important; height:300px !important; }
          .dgptm-qrbox img{ width:300px; height:300px; object-fit:contain; display:block; }
          .dgptm-qr-actions{ margin-top:10px; display:none; }
          .dgptm-qr-actions .btn{display:inline-block;padding:6px 10px;border:1px solid #888;border-radius:6px;background:#f5f5f5;cursor:pointer}
          .dgptm-fullred{ position:fixed; inset:0; background:#ff3131; z-index:9999; display:none; align-items:center; justify-content:center; color:#fff; font-weight:900; font-size: clamp(28px, 6vw, 80px); }
          .dgptm-badge { display:inline-block; padding:2px 8px; background:#eef; border:1px solid #ccd; border-radius:999px; font-size:12px; margin-left:6px; }
          .dgptm-muted{ color:#666; font-size:12px; text-align:center; margin-top:6px; }
        </style>

        <div class="dgptm-regwrap">
          <div class="dgptm-big">Registrierungsmonitor</div>
          <div class="dgptm-sub">
            <?php if(!$enabled): ?>
              <strong style="color:#a00">Achtung:</strong> Registrierung ist <strong>deaktiviert</strong> (im Manager aktivieren).
            <?php else: ?>
              Registrierung aktiv für Umfrage-ID <strong><?php echo (int)$targetPid; ?></strong>
            <?php endif; ?>
          </div>

          <div class="dgptm-regrow">
            <div class="dgptm-regbox">
              <label for="dgptm_scan_input" style="display:block; font-weight:700; margin-bottom:6px;">Scanner / Eingabe</label>
              <input id="dgptm_scan_input" class="dgptm-input" autocomplete="one-time-code" inputmode="text" placeholder="Code scannen oder eintippen & Enter">
              <div id="dgptm_status" class="dgptm-status" style="display:none;"></div>
              <div id="dgptm_last" style="margin-top:10px;color:#444;font-size:14px;"></div>
              <div class="dgptm-muted">Tipp: Das Eingabefeld behält automatisch den Fokus – einfach scannen.</div>
            </div>

            <div class="dgptm-regbox">
              <div class="dgptm-sub">QR-Code für persönlichen Teilnahme-Link</div>
              <div id="dgptm_qr" class="dgptm-qrbox">
                <em style="display:none;">Noch kein Code …</em>
              </div>
              <div class="dgptm-qr-actions" id="dgptm_qr_actions">
                <button type="button" class="btn" id="dgptm_close_qr">Schließen</button>
              </div>
              <div class="dgptm-muted">Der QR-Code verschwindet automatisch nach 30 Sekunden.</div>
            </div>
          </div>
        </div>
        <div id="dgptm_fullred" class="dgptm-fullred">UNGÜLTIG</div>

        <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
        <script>
        (function(){
          var qrTimerId=null;

          function el(id){ return document.getElementById(id); }
          function showRed(msg){
            var overlay=el('dgptm_fullred');
            overlay.style.display='flex';
            setTimeout(function(){ overlay.style.display='none'; }, 1600);
            var s=el('dgptm_status');
            s.className='dgptm-status err'; s.style.display='block';
            s.textContent = msg || 'Ungültig';
          }
          function showOk(text){
            var s=el('dgptm_status');
            s.className='dgptm-status ok'; s.style.display='block';
            s.textContent = text || 'Registriert';
          }
          function encode(s){ try{return encodeURIComponent(s);}catch(e){return s;} }

          function drawQR(url){
            var box=el('dgptm_qr');
            box.innerHTML='';
            var usedCanvas = false;

            try{
              if (typeof QRCode!=='undefined' && QRCode.toCanvas) {
                var cv=document.createElement('canvas');
                box.appendChild(cv);
                QRCode.toCanvas(cv, url, {width: 300, margin:1, errorCorrectionLevel:'M'});
                usedCanvas = true;
              }
            }catch(e){ usedCanvas = false; }

            if(!usedCanvas){
              var img = document.createElement('img');
              img.alt = 'QR';
              img.width = 300; img.height = 300;
              img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' + encode(url);
              box.appendChild(img);
            }
          }
          function resetQR(){
            if(qrTimerId){ clearTimeout(qrTimerId); qrTimerId=null; }
            var box=el('dgptm_qr');
            box.style.display='none';
            box.innerHTML='<em style="display:none;">Noch kein Code …</em>';
            el('dgptm_qr_actions').style.display='none';
          }
          function showQR(url){
            drawQR(url);
            var box=el('dgptm_qr');
            box.style.display='flex';
            el('dgptm_qr_actions').style.display='block';
            if(qrTimerId){ clearTimeout(qrTimerId); }
            qrTimerId=setTimeout(resetQR, 30000); // 30s Timeout
          }

          var input = el('dgptm_scan_input');
          input.addEventListener('keydown', function(e){
            if(e.key==='Enter'){
              e.preventDefault();
              var val = input.value.trim();
              if(!val){ return; }
              var s=el('dgptm_status');
              s.style.display='none';

              var body = new URLSearchParams({action:'dgptm_scan_register', code: val});
              fetch("<?php echo esc_js(admin_url('admin-ajax.php')); ?>", {
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
                body: body.toString()
              }).then(function(r){ return r.json(); }).then(function(resp){
                if(!(resp && resp.success)){
                  showRed(resp && resp.data ? resp.data : 'Fehler');
                  return;
                }
                var d = resp.data || {};
                if(!d.ok){
                  showRed(d.msg || 'Ungültig');
                  return;
                }
                showOk('OK: '+ (d.display || 'registriert'));
                if(d.qr_url){ showQR(d.qr_url); }
                el('dgptm_last').innerHTML = 'Zuletzt: <strong>'+(d.display || '')+'</strong>'+(d.member_no?'<span class="dgptm-badge">#'+d.member_no+'</span>':'');
                input.value='';
              }).catch(function(){
                showRed('Fehler');
              });
            }
          });

          setInterval(function(){ if(document.activeElement!==input){ input.focus(); }}, 800);
          input.focus();

          el('dgptm_close_qr').addEventListener('click', resetQR);
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}

if (!shortcode_exists('dgptm_registration_monitor')) {
    add_shortcode('dgptm_registration_monitor','dgptm_registration_monitor_fn');
}
