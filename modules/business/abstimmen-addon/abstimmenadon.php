<?php
/**
 * Plugin Name: DGPTM Presence Scanner – Manual Lookup Addon
 * Description: Erweitert/überschreibt [dgptm_presence_scanner] um „Manuelle Abfrage“ (Namenssuche per Doppelklick), markiert manuelle Einträge und setzt Status=Mitgliedsart wie beim Codescan.
 * Version:     1.1.0
 * Author:      DGPTM
 */

if (!defined('ABSPATH')) { exit; }

final class DGPTM_Presence_Manual_Addon {
    const REST_NS          = 'dgptm-addon/v1';
    // Die Konstanten für den Webhook und den Token werden nicht mehr benötigt.
    // const MANUAL_WEBHOOK   = 'https://perfusiologie.de/wp-json/dgptm/v1/mvvmanuell';
    // const MANUAL_TOKEN     = 'UUDEh3qf41XgATMfCRJcOWxE7CgxMsXp';

    public static function init(){
        add_action('init', [__CLASS__,'register_shortcode'], 99);
        add_action('rest_api_init', [__CLASS__,'register_rest']);
    }

    public static function register_shortcode(){
        remove_shortcode('dgptm_presence_scanner');
        add_shortcode('dgptm_presence_scanner', [__CLASS__, 'shortcode']);
    }

    public static function shortcode($atts){
        $opts = get_option('dgptm_vote_settings', []);

        // Der Parameter 'search_webhook' wird nun intern über die REST-API von WordPress aufgelöst.
        $a = shortcode_atts([
            'webhook'        => $opts['presence_webhook_url'] ?? '',
            'meeting_number' => $opts['zoom_meeting_number'] ?? '',
            'kind'           => $opts['zoom_kind'] ?? 'auto',
            'save_on'        => 'green,yellow',
            'search_webhook' => rest_url('dgptm/v1/mvvmanuell'), // Direkte interne Route
        ], $atts, 'dgptm_presence_scanner');

        $mid   = preg_replace('/\D/','', (string)$a['meeting_number']);
        $kind  = strtolower((string)$a['kind']);
        if (!in_array($kind,['auto','meeting','webinar'], true)) { $kind = 'auto'; }
        $webhook        = esc_url_raw($a['webhook']);
        $search_webhook = esc_url_raw($a['search_webhook']);

        ob_start(); ?>
        <div class="dgptm-presence"
             data-webhook="<?php echo esc_attr($webhook); ?>"
             data-search-webhook="<?php echo esc_attr($search_webhook); ?>"
             
             data-meeting="<?php echo esc_attr($mid); ?>"
             data-kind="<?php echo esc_attr($kind); ?>"
             data-saveon="<?php echo esc_attr($a['save_on']); ?>">

          <div class="flash"></div>

          <div style="display:flex;gap:.5rem;align-items:center;justify-content:center;margin-bottom:.5rem">
            <input type="text" class="scan-input" placeholder="Code scannen &amp; Enter" autofocus />
            <button type="button" id="dgptm-manual-open" class="button">Manuelle Abfrage</button>
          </div>

          <div class="info" aria-live="polite"></div>
          <div class="sub"></div>
          <div class="hint">
            Hinweis: <b>Badge scannen oder „Manuelle Abfrage“ nutzen.</b>
          </div>

          <div id="dgptm-last-entries" style="max-width:720px;width:100%;margin-top:1rem"></div>
        </div>

        <!-- Modal -->
        <div id="dgptm-search-modal" class="dgptm-modal" style="display:none">
          <div class="dgptm-modal-content">
            <div class="dgptm-modal-header">
              <h3>Mitglied suchen</h3>
              <span class="dgptm-modal-close" role="button" aria-label="schließen">&times;</span>
            </div>
            <div class="dgptm-modal-body">
              <label for="dgptm-search-input" class="screen-reader-text">Name</label>
              <input type="text" id="dgptm-search-input" placeholder="Name eingeben…" autocomplete="off" />
              <button type="button" id="dgptm-search-execute-btn" class="button button-primary">Suchen</button>
              <div style="margin-top:.5rem;font-size:.9em;opacity:.8">Tipp: <em>Doppelklick</em> auf ein Ergebnis übernimmt den Eintrag sofort.</div>
              <div id="dgptm-search-results" style="margin-top:.75rem"></div>
            </div>
            <div class="dgptm-modal-footer" style="display:flex;gap:.5rem;justify-content:flex-end">
              <button type="button" id="dgptm-search-cancel-btn" class="button">Abbruch</button>
            </div>
          </div>
        </div>

        <style>
          .dgptm-modal{position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:10000}
          .dgptm-modal-content{background:#fff;margin:5vh auto;max-width:900px;padding:1rem;border-radius:8px}
          .dgptm-search-result-item{padding:10px;border:1px solid #ddd;margin:5px 0;cursor:pointer;background:#f9f9f9}
          .dgptm-search-result-item:hover{background:#e0f7fa}
          .dgptm-search-result-item.selected{background:#4CAF50;color:#fff}
          .dgptm-manual-entry .dgptm-status-M{color:#856404;font-weight:bold}
          .dgptm-entry{padding:10px;margin-top:.5rem;border:1px dashed #ccc;border-radius:6px}
          .dgptm-entry-header{font-weight:600}
          .dgptm-entry-time{font-size:12px;opacity:.75}
          .dgptm-row{display:grid;grid-template-columns:160px 1fr;gap:.25rem .5rem;font-size:.92em}
          .dgptm-row b{opacity:.8}
        </style>

        <script>
        (function(){
          function normalizeWebhookResponse(d){
            try{
              if(d && typeof d==="object"){
                if(Array.isArray(d)) return d;
                if(d.results && Array.isArray(d.results)) return d.results;
                if(d.details && d.details.output){
                  const out = d.details.output;
                  if(Array.isArray(out)) return out;
                  if(out.results && Array.isArray(out.results)) return out.results;
                }
              }
            }catch(_){}
            return [];
          }

          function titleCaseName(s){
            if(!s) return s;
            return String(s).trim()
              .split(/\s+/)
              .map(w => w.charAt(0).toUpperCase() + w.slice(1))
              .join(' ');
          }

          function openSearchModal(){
            document.getElementById('dgptm-search-modal').style.display='block';
            const inp = document.getElementById('dgptm-search-input');
            inp.focus();
            inp.selectionStart = 0; inp.selectionEnd = inp.value.length;
          }
          function closeSearchModal(){
            document.getElementById('dgptm-search-modal').style.display='none';
            document.getElementById('dgptm-search-input').value='';
            document.getElementById('dgptm-search-results').innerHTML='';
          }

          document.addEventListener('click', function(ev){
            if(ev.target && ev.target.id==='dgptm-manual-open'){ ev.preventDefault(); openSearchModal(); }
            if(ev.target && (ev.target.classList.contains('dgptm-modal-close') || ev.target.id==='dgptm-search-cancel-btn')){ ev.preventDefault(); closeSearchModal(); }
            if(ev.target && ev.target.id==='dgptm-search-execute-btn'){ ev.preventDefault(); executeSearch(); }
          });
          document.getElementById('dgptm-search-modal')?.addEventListener('click', function(e){ if(e.target===this){ closeSearchModal(); }});

          async function executeSearch(){
            const raw = (document.getElementById('dgptm-search-input').value||'').trim();
            if(!raw){ alert('Bitte Name eingeben.'); return; }
            const name = titleCaseName(raw);

            const box   = document.querySelector('.dgptm-presence');
            const url   = box?.dataset.searchWebhook || '';
            const out   = document.getElementById('dgptm-search-results');
            if(!url) { out.innerHTML = '<span style="color:#c00">Fehler: Such-Endpunkt nicht konfiguriert.</span>'; return; }
            out.innerHTML = '<em>Suche läuft…</em>';

            try{
              // Der Token wird nicht mehr benötigt. Die Authentifizierung erfolgt jetzt
              // über den WP-Nonce für den eingeloggten Benutzer.
              const r = await fetch(url, {
                method: 'POST',
                headers: {
                  'Content-Type':'application/json',
                  'X-WP-Nonce': (window.dgptm_vote?.rest_nonce) || ''
                },
                credentials: 'same-origin', // Wichtig für die Cookie-basierte Authentifizierung
                body: JSON.stringify({ name: name, query: name })
              });
              if(!r.ok){ throw new Error('HTTP '+r.status); }
              const json = await r.json();
              const results = normalizeWebhookResponse(json);
              renderResults(results);
            }catch(e){
              console.error(e);
              out.innerHTML = '<span style="color:#c00">Fehler bei der Suche.</span>';
            }
          }

          function getFirst(v1, v2){ return (v1!==undefined && v1!==null && v1!=='') ? v1 : v2; }

          function renderResults(results){
            const out = document.getElementById('dgptm-search-results');
            if(!Array.isArray(results) || !results.length){ out.innerHTML = '<em>Keine Ergebnisse.</em>'; return; }
            let html = '<div role="list" aria-label="Suchergebnisse (Doppelklick übernimmt)">';
            results.forEach((m,idx)=>{
              const dispName = titleCaseName(getFirst(m.fullname, m.name) || 'Unbekannt');
              const email = getFirst(m.email, m.Email) || '';
              const mitgliedsart = getFirst(m.Mitgliedsart, m.mitgliedsart) || '–';
              const status = getFirst(m.status, m.Status) || '–';
              const mitgliedsnummer = getFirst(m.mitgliedsnummer, m.Mitgliedsnummer) || '–';

              const merged = Object.assign({}, m, {
                fullname: dispName,
                email: email,
                Mitgliedsart: mitgliedsart,
                status: status,
                mitgliedsnummer: mitgliedsnummer
              });
              const safe = JSON.stringify(merged).replace(/"/g,'&quot;');

              html += '<div class="dgptm-search-result-item" role="listitem" data-index="'+idx+'" data-member="'+safe+'" title="Doppelklick übernimmt">'+
                        '<div><strong>'+dispName+'</strong></div>'+
                        '<div class="dgptm-row"><b>Email</b><span>'+(email||'–')+'</span></div>'+
                        '<div class="dgptm-row"><b>Status</b><span>'+(status||'–')+'</span></div>'+
                        '<div class="dgptm-row"><b>Mitgliedsart</b><span>'+(mitgliedsart||'–')+'</span></div>'+
                        '<div class="dgptm-row"><b>Mitgliedsnummer</b><span>'+(mitgliedsnummer||'–')+'</span></div>'+
                      '</div>';
            });
            html += '</div>';
            out.innerHTML = html;

            document.querySelectorAll('.dgptm-search-result-item').forEach(el=>{
              el.addEventListener('click', function(){
                document.querySelectorAll('.dgptm-search-result-item').forEach(x=>x.classList.remove('selected'));
                this.classList.add('selected');
              });
              el.addEventListener('dblclick', function(){
                try{
                  const data = JSON.parse(this.dataset.member || '{}');
                  addSelectedMember(data);
                }catch(e){ console.error(e); }
              });
            });
          }

          async function addSelectedMember(presetData){
            const box  = document.querySelector('.dgptm-presence');
            let data = presetData;
            if(!data){
              const sel = document.querySelector('.dgptm-search-result-item.selected');
              if(!sel){ alert('Bitte einen Eintrag auswählen oder doppelklicken.'); return; }
              data = JSON.parse(sel.dataset.member || '{}');
            }
            data.fullname = titleCaseName(data.fullname || data.name || '');

            try{
              await saveMemberToDatabase(data, box, /*manual*/true);
              pushManualEntryToList(data);
              closeSearchModal();
            }catch(e){ alert('Speichern fehlgeschlagen: '+(e?.message||e)); }
          }

          function pushManualEntryToList(data){
            const list = document.getElementById('dgptm-last-entries');
            const ts   = new Date();
            const div  = document.createElement('div');
            div.className = 'dgptm-entry dgptm-manual-entry';
            // Anzeige „Manuell: X“ (statt nur Text)
            div.innerHTML =
              '<div class="dgptm-entry-header">'+(data.fullname||data.name||'Unbekannt')+
              ' <span class="dgptm-entry-time">'+ts.toLocaleTimeString()+'</span></div>'+
              '<div class="dgptm-entry-details">'+
                 '<span>E-Mail: '+(data.email||'–')+'</span>'+
                 ' · <span>Mitgliedsart: '+(data.Mitgliedsart||data.mitgliedsart||'–')+'</span>'+
                 ' · <span><b>Manuell: X</b></span>'+
              '</div>';
            list.prepend(div);
          }

          async function saveMemberToDatabase(data, container, manual){
            const mid  = String(container?.dataset.meeting||'');
            const kind = String(container?.dataset.kind||'auto');

            const mitgliedsart = data.Mitgliedsart || data.mitgliedsart || '';
            // Wichtig: Status = Mitgliedsart (wie beim Codescan)
            const status_for_table = mitgliedsart || (data.status || data.Status || '');

            const payload = {
              id: mid,
              kind: kind,
              name:  data.fullname || data.name || '',
              email: data.email || '',
              status: status_for_table,                 // <-- Status = Mitgliedsart
              mitgliedsart: mitgliedsart,
              mitgliedsnummer: data.mitgliedsnummer || data.Mitgliedsnummer || '',
              ts: Math.floor(Date.now()/1000),
              manual: manual ? 1 : 0
            };

            if(!window.dgptm_vote || !dgptm_vote.rest_presence){ throw new Error('REST nicht verfügbar'); }

            // 1) an bestehendes REST des Hauptplugins schicken
            const r = await fetch(dgptm_vote.rest_presence, {
              method: 'POST',
              headers: { 'Content-Type':'application/json', 'X-WP-Nonce': dgptm_vote.rest_nonce },
              credentials: 'same-origin',
              body: JSON.stringify(payload)
            });
            if(!r.ok){ throw new Error('HTTP '+r.status); }

            // 2) „manual“-Flag + Felder in unserem Store spiegeln (falls Haupt-REST es nicht speichert)
            await fetch('<?php echo esc_js( rest_url( self::REST_NS.'/mark-manual' ) ); ?>', {
              method: 'POST',
              headers: { 'Content-Type':'application/json', 'X-WP-Nonce': dgptm_vote.rest_nonce },
              credentials: 'same-origin',
              body: JSON.stringify({
                id: mid, kind: kind,
                name: payload.name, email: payload.email,
                status: payload.status,                 // hier ebenfalls Status = Mitgliedsart
                mitgliedsart: payload.mitgliedsart,
                mitgliedsnummer: payload.mitgliedsnummer
              })
            });
          }

          // Tabelle weiterhin mit Spalte "Manuell" (X) versehen
          function patchPresenceTables(){
            document.querySelectorAll('.dgptm-presence-ui').forEach(box=>{
              ensureManualHeader(box);
              applyManualFlags(box);
              const tbody = box.querySelector('tbody');
              if(!tbody || tbody.dataset.dgptmObserver){ return; }
              const obs = new MutationObserver(()=>{ ensureManualHeader(box); applyManualFlags(box); });
              obs.observe(tbody, { childList:true, subtree:true });
              tbody.dataset.dgptmObserver = '1';
            });
          }

          function ensureManualHeader(box){
            const thead = box.querySelector('thead tr');
            if(!thead) return;
            const heads = thead.querySelectorAll('th');
            const already = Array.from(heads).some(th=>th.textContent.trim().toLowerCase()==='manuell');
            if(!already){
              const th = document.createElement('th'); th.textContent = 'Manuell';
              thead.insertBefore(th, thead.lastElementChild);
              const td = box.querySelector('tbody td[colspan="6"]'); if(td){ td.setAttribute('colspan','7'); }
            }
          }

          async function applyManualFlags(box){
            const mid  = String(box.dataset.meeting||'');
            const kind = String(box.dataset.kind||'auto');
            try{
              const r = await fetch('<?php echo esc_js( rest_url( self::REST_NS.'/manual-flags' ) ); ?>?id='+encodeURIComponent(mid)+'&kind='+encodeURIComponent(kind), {
                headers: { 'X-WP-Nonce': (window.dgptm_vote?.rest_nonce)||'' }, credentials:'same-origin'
              });
              const json = await r.json();
              const set  = new Set(Array.isArray(json?.manual_pks) ? json.manual_pks : []);
              const rows = box.querySelectorAll('tbody tr');
              rows.forEach(tr=>{
                const old = tr.querySelector('td.dgptm-manual-cell'); if(old){ old.remove(); }
                const actions = tr.querySelector('td:last-child');
                const td = document.createElement('td'); td.className='dgptm-manual-cell';
                td.textContent = set.has(tr.getAttribute('data-pk')) ? 'X' : '';
                tr.insertBefore(td, actions);
              });
            }catch(e){ /* ignore */ }
          }

          document.addEventListener('DOMContentLoaded', patchPresenceTables);
          setInterval(patchPresenceTables, 5000);
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    public static function register_rest(){
        register_rest_route(self::REST_NS, '/manual-flags', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'rest_manual_flags'],
            'permission_callback' => function(){ return current_user_can('edit_posts'); }
        ]);
        register_rest_route(self::REST_NS, '/mark-manual', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_mark_manual'],
            'permission_callback' => function(){ return current_user_can('edit_posts'); }
        ]);
    }

    public static function rest_manual_flags(\WP_REST_Request $req){
        $id   = preg_replace('/\D/','', (string)$req->get_param('id'));
        $kind = strtolower((string)($req->get_param('kind') ?: 'auto'));
        if(!$id){ return new \WP_REST_Response(['ok'=>false,'error'=>'missing id'], 400); }

        $store = get_option('dgptm_zoom_attendance', []);
        $key   = strtolower($kind).':'.$id;
        $rows  = isset($store[$key]['participants']) && is_array($store[$key]['participants']) ? $store[$key]['participants'] : [];

        $manual = [];
        foreach($rows as $pk => $r){
            if(!empty($r['manual'])){ $manual[] = (string)$pk; }
        }
        return new \WP_REST_Response(['ok'=>true,'manual_pks'=>$manual], 200);
    }

    public static function rest_mark_manual(\WP_REST_Request $req){
        $p     = $req->get_json_params();
        $id    = isset($p['id'])   ? preg_replace('/\D/','', (string)$p['id']) : '';
        $kind  = isset($p['kind']) ? strtolower((string)$p['kind']) : 'auto';
        $name  = trim((string)($p['name']  ?? ''));
        $email = trim((string)($p['email'] ?? ''));
        $status = trim((string)($p['status'] ?? ''));               // hier bereits = Mitgliedsart
        $mitgliedsart = trim((string)($p['mitgliedsart'] ?? ''));
        $mitgliedsnummer = trim((string)($p['mitgliedsnummer'] ?? ''));
        if(!$id){ return new \WP_REST_Response(['ok'=>false,'error'=>'missing id'], 400); }

        $key   = strtolower($kind).':'.$id;
        $store = get_option('dgptm_zoom_attendance', []);
        if(!isset($store[$key]['participants']) || !is_array($store[$key]['participants'])){
            $store[$key]['participants'] = [];
        }
        $pk = $email !== '' ? ('mail:'.strtolower($email)) : ('name:'.md5($name!==''?$name:'Unknown'));

        if(!isset($store[$key]['participants'][$pk])){
            $store[$key]['participants'][$pk] = [
                'type'=>'presence','name'=>$name,'email'=>$email,'status'=>$status,
                'join_first'=>time(),'leave_last'=>0,'total'=>0,'sessions'=>[]
            ];
        } else {
            if($name!=='')  { $store[$key]['participants'][$pk]['name']   = $name; }
            if($email!=='') { $store[$key]['participants'][$pk]['email']  = $email; }
            if($status!==''){ $store[$key]['participants'][$pk]['status'] = $status; }
        }
        if($mitgliedsart!==''){     $store[$key]['participants'][$pk]['mitgliedsart']     = $mitgliedsart; }
        if($mitgliedsnummer!==''){  $store[$key]['participants'][$pk]['mitgliedsnummer']  = $mitgliedsnummer; }

        $store[$key]['participants'][$pk]['manual'] = 1;
        update_option('dgptm_zoom_attendance', $store, false);

        return new \WP_REST_Response(['ok'=>true], 200);
    }
}

DGPTM_Presence_Manual_Addon::init();
