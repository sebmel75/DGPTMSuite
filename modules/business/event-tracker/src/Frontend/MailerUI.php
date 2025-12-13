<?php
/**
 * Event Tracker Mailer UI (aus Backup portiert)
 *
 * @package EventTracker\Frontend
 * @since 2.0.1
 */

namespace EventTracker\Frontend;

use EventTracker\Core\Constants;
use EventTracker\Core\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mailer UI Class - rendert das Mail-Formular
 */
class MailerUI {

	/**
	 * Script enqueued flag
	 *
	 * @var bool
	 */
	private static $script_enqueued = false;

	/**
	 * Render Mail UI
	 *
	 * @return string HTML
	 */
	public static function render() {
		// Enqueue WordPress editor
		Helpers::begin_cap_override();
		if ( function_exists( 'wp_enqueue_editor' ) ) {
			wp_enqueue_editor();
		}
		if ( function_exists( 'wp_enqueue_media' ) ) {
			wp_enqueue_media();
		}
		Helpers::end_cap_override();

		// Enqueue mailer script
		self::enqueue_mailer_script();

		$ajax_url  = esc_url( admin_url( 'admin-ajax.php' ) );
		$nonce     = wp_create_nonce( 'et_mailer' );
		$editor_id = 'etm_html_' . uniqid();

		// Get future/current events
		$events = get_posts(
			[
				'post_type'      => Constants::CPT,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'meta_value_num',
				'meta_key'       => Constants::META_START_TS,
				'order'          => 'ASC',
				'fields'         => 'ids',
			]
		);

		// Get mail templates
		$templates = get_posts(
			[
				'post_type'      => Constants::CPT_MAIL_TPL,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			]
		);

		$now        = time();
		$event_opts = [];
		foreach ( $events as $eid ) {
			$start = (int) get_post_meta( $eid, Constants::META_START_TS, true );
			$end   = (int) get_post_meta( $eid, Constants::META_END_TS, true );
			if ( ( $start && $end && $end >= $now ) || ( $start && ! $end ) ) {
				$event_opts[] = $eid;
			}
		}

		// Get mail logs
		$logs = get_posts(
			[
				'post_type'      => Constants::CPT_MAIL_LOG,
				'post_status'    => 'any',
				'posts_per_page' => 50,
				'orderby'        => 'date',
				'order'          => 'DESC',
			]
		);

		$tz = wp_timezone();
		$df = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		ob_start();
		?>
		<style>
			.etm-wrap{border:1px solid #e5e7eb;border-radius:12px;padding:16px;margin:24px 0;max-width:980px}
			.etm-grid{display:grid;grid-template-columns:1fr;gap:12px}
			.etm-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
			.etm-row .full{grid-column:1/-1}
			.etm-inline{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
			.etm-input, .etm-select{width:100%;padding:8px;border:1px solid #d1d5db;border-radius:6px}
			.etm-help{font-size:.9rem;color:#6b7280;margin-top:4px}
			.etm-btn{display:inline-flex;align-items:center;gap:.5rem;border:1px solid #e5e7eb;border-radius:8px;padding:.5rem .8rem;background:#f9fafb;cursor:pointer;font-size:.95rem}
			.etm-btn.primary{background:#2563eb;color:#fff;border-color:#2563eb}
			.etm-btn.danger{background:#ef4444;color:#fff;border-color:#ef4444}
			.etm-btn[disabled]{opacity:.6;pointer-events:none}
			.etm-table{width:100%;border-collapse:collapse;margin-top:16px}
			.etm-table th,.etm-table td{border-bottom:1px solid #e5e7eb;padding:8px;text-align:left;vertical-align:top}
			.etm-table th{background:#f9fafb;font-weight:600}
			.etm-badge{display:inline-block;font-size:.75rem;padding:.15rem .5rem;border-radius:.5rem;background:#e5e7eb}
			.etm-badge.sent{background:#d1fae5;color:#065f46}
			.etm-badge.queued{background:#fef3c7;color:#92400e}
			.etm-badge.error{background:#fee2e2;color:#991b1b}
			.etm-badge.stopped{background:#cbd5e1;color:#475569}
			.etm-msg{padding:10px;border-radius:6px;margin:10px 0;display:none}
			.etm-msg.show{display:block}
			.etm-switch{position:relative;display:inline-block;width:46px;height:26px;vertical-align:middle}
			.etm-switch input{opacity:0;width:0;height:0}
			.etm-slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#d1d5db;transition:.2s;border-radius:999px}
			.etm-slider:before{position:absolute;content:"";height:20px;width:20px;left:3px;bottom:3px;background:white;transition:.2s;border-radius:50%}
			.etm-switch input:checked + .etm-slider{background:#16a34a}
			.etm-switch input:checked + .etm-slider:before{transform:translateX(20px)}
			@media (max-width:800px){.etm-row{grid-template-columns:1fr}}
		</style>

		<div class="etm-wrap" data-ajax="<?php echo $ajax_url; ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-editor="<?php echo esc_attr( $editor_id ); ?>">
			<h3><?php esc_html_e( 'HTML-Mail verfassen & versenden', 'event-tracker' ); ?></h3>
			<div class="etm-help">
				<?php echo wp_kses_post( __( 'Der Platzhalter <code>{{URL}}</code> wird durch einen funktionierenden Link ersetzt. Der Platzhalter kann auch innerhalb von <code>&lt;a&gt;</code>-Links oder Buttons verwendet werden. Der Platzhalter <code>{{NAME}}</code> wird durch den Namen des Teilnehmers ersetzt.', 'event-tracker' ) ); ?>
			</div>

			<div class="etm-grid">
				<div class="etm-row">
					<div class="full">
						<label><strong><?php esc_html_e( 'Veranstaltung auswählen', 'event-tracker' ); ?></strong></label>
						<select class="etm-select etm-event-id">
							<option value=""><?php esc_html_e( '— Veranstaltung wählen —', 'event-tracker' ); ?></option>
							<?php
							foreach ( $event_opts as $eid ) :
								$start = (int) get_post_meta( $eid, Constants::META_START_TS, true );
								$end   = (int) get_post_meta( $eid, Constants::META_END_TS, true );
								$label = get_the_title( $eid );
								if ( $start ) {
									$label .= ' – ' . wp_date( $df, $start, $tz );
								}
								if ( $end ) {
									$label .= ' → ' . wp_date( $df, $end, $tz );
								}
								?>
								<option value="<?php echo esc_attr( $eid ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<div class="etm-help"><?php esc_html_e( 'Es werden nur laufende oder zukünftige Veranstaltungen angezeigt.', 'event-tracker' ); ?></div>
					</div>
				</div>

				<div class="etm-row">
					<div class="full">
						<label><strong><?php esc_html_e( 'Mail-Vorlage', 'event-tracker' ); ?></strong></label>
						<div class="etm-inline">
							<select class="etm-select etm-template-id" style="flex:1;min-width:0">
								<option value=""><?php esc_html_e( '— Vorlage wählen —', 'event-tracker' ); ?></option>
								<?php foreach ( $templates as $tpl ) : ?>
									<option value="<?php echo esc_attr( $tpl->ID ); ?>"><?php echo esc_html( $tpl->post_title ); ?></option>
								<?php endforeach; ?>
							</select>
							<button type="button" class="etm-btn" data-action="load-template"><?php esc_html_e( 'Laden', 'event-tracker' ); ?></button>
							<button type="button" class="etm-btn" data-action="save-template"><?php esc_html_e( 'Als Vorlage speichern', 'event-tracker' ); ?></button>
							<button type="button" class="etm-btn danger" data-action="delete-template"><?php esc_html_e( 'Löschen', 'event-tracker' ); ?></button>
						</div>
						<div class="etm-help"><?php esc_html_e( 'Wiederverwendbare Vorlagen für häufig genutzte Mail-Layouts.', 'event-tracker' ); ?></div>
					</div>
				</div>

				<div class="etm-row">
					<div class="full">
						<label><strong><?php esc_html_e( 'Betreff', 'event-tracker' ); ?></strong></label>
						<input type="text" class="etm-input etm-subject" placeholder="<?php esc_attr_e( 'Betreff der E-Mail', 'event-tracker' ); ?>">
					</div>
				</div>

				<div class="etm-row">
					<div class="full">
						<label><strong><?php esc_html_e( 'HTML-Mail', 'event-tracker' ); ?></strong></label>
						<div class="etm-editor-wrap">
							<?php
							Helpers::begin_cap_override();
							$settings = [
								'textarea_name' => $editor_id,
								'editor_height' => 320,
								'media_buttons' => current_user_can( 'upload_files' ),
								'tinymce'       => true,
								'quicktags'     => true,
							];
							wp_editor( '', $editor_id, $settings );
							Helpers::end_cap_override();
							?>
						</div>
					</div>
				</div>

				<div class="etm-row">
					<div class="full">
						<label><strong><?php esc_html_e( 'Sendezeitpunkt', 'event-tracker' ); ?></strong></label>
						<div class="etm-inline">
							<label><input type="radio" name="etm-when" value="now" checked> <?php esc_html_e( 'Sofort', 'event-tracker' ); ?></label>
							<label><input type="radio" name="etm-when" value="event_start"> <?php esc_html_e( 'Zu Veranstaltungsbeginn', 'event-tracker' ); ?></label>
							<label><input type="radio" name="etm-when" value="at"> <?php esc_html_e( 'Am', 'event-tracker' ); ?></label>
							<input type="datetime-local" class="etm-input etm-when-at" style="max-width:260px;display:none" />
							<label><input type="radio" name="etm-when" value="until_start"> <?php esc_html_e( 'Intervall bis Veranstaltungsbeginn', 'event-tracker' ); ?></label>
							<select class="etm-input etm-interval-min" style="max-width:220px;display:none">
								<option value=""><?php esc_html_e( '— Intervall wählen —', 'event-tracker' ); ?></option>
								<option value="5">5 <?php esc_html_e( 'Minuten', 'event-tracker' ); ?></option>
								<option value="15">15 <?php esc_html_e( 'Minuten', 'event-tracker' ); ?></option>
								<option value="30">30 <?php esc_html_e( 'Minuten', 'event-tracker' ); ?></option>
								<option value="60">60 <?php esc_html_e( 'Minuten', 'event-tracker' ); ?></option>
								<option value="120">120 <?php esc_html_e( 'Minuten', 'event-tracker' ); ?></option>
							</select>
							<input type="datetime-local" class="etm-input etm-interval-start" style="max-width:260px;display:none" placeholder="<?php esc_attr_e( 'Intervall-Start (optional)', 'event-tracker' ); ?>" />
							<span class="etm-help"><?php echo esc_html( sprintf( __( 'Zeitzone: %s', 'event-tracker' ), wp_timezone_string() ) ); ?></span>
						</div>
						<div class="etm-help"><?php esc_html_e( 'Optional: Intervall erst ab einem bestimmten Zeitpunkt starten (z. B. morgen 08:00).', 'event-tracker' ); ?></div>
					</div>
				</div>

				<div class="etm-row">
					<div class="full">
						<div class="etm-inline">
							<label class="etm-switch" title="<?php echo esc_attr__( 'Pro Veranstaltung wird normalerweise nur eine Nachricht verschickt. Durch Aktivierung dieses Felds wird diese Regel außer Kraft gesetzt und die Nachricht trotzdem verschickt.', 'event-tracker' ); ?>">
								<input type="checkbox" class="etm-toggle-ignoredate" id="etm_ignoredate" />
								<span class="etm-slider"></span>
							</label>
							<label for="etm_ignoredate"><strong><?php esc_html_e( 'Datumsprüfung deaktivieren', 'event-tracker' ); ?></strong></label>
						</div>
						<div class="etm-help"><?php esc_html_e( 'Pro Veranstaltung wird pro Tag normalerweise nur eine Nachricht verschickt. Durch Aktivierung dieses Felds wird diese Regel außer Kraft gesetzt und die Nachricht trotzdem verschickt.', 'event-tracker' ); ?></div>
					</div>
				</div>

				<div class="etm-row">
					<div class="full etm-inline">
						<button type="button" class="etm-btn primary" data-action="send-submit"><?php esc_html_e( 'Abschicken / Planen', 'event-tracker' ); ?></button>
					</div>
				</div>

				<div class="etm-row">
					<div class="etm-inline full">
						<input type="email" class="etm-input etm-test-email" placeholder="<?php esc_attr_e( 'Test-E-Mail-Adresse', 'event-tracker' ); ?>" />
						<button type="button" class="etm-btn" data-action="send-test"><?php esc_html_e( 'Testmail senden ({{URL}} ersetzen)', 'event-tracker' ); ?></button>
					</div>
				</div>

				<div class="etm-msg etm-help"></div>
			</div>

			<h3 style="margin-top:24px"><?php esc_html_e( 'Verschickte & geplante Mails', 'event-tracker' ); ?></h3>
			<table class="etm-table etm-log-table">
				<thead>
				<tr>
					<th><?php esc_html_e( 'Datum', 'event-tracker' ); ?></th>
					<th><?php esc_html_e( 'Event', 'event-tracker' ); ?></th>
					<th><?php esc_html_e( 'Status', 'event-tracker' ); ?></th>
					<th><?php esc_html_e( 'Aktionen', 'event-tracker' ); ?></th>
				</tr>
				</thead>
				<tbody>
				<?php if ( $logs ) :
					foreach ( $logs as $log ) :
						$event_id = (int) get_post_meta( $log->ID, Constants::META_MAIL_EVENT_ID, true );
						$status   = (string) get_post_meta( $log->ID, Constants::META_MAIL_STATUS, true );
						$sched_ts = (int) get_post_meta( $log->ID, Constants::META_MAIL_SCHED_TS, true );
						$is_rec   = get_post_meta( $log->ID, Constants::META_MAIL_RECURRING, true ) === '1';
						$stopped  = get_post_meta( $log->ID, Constants::META_MAIL_STOPPED, true ) === '1';
						$subject  = (string) get_post_meta( $log->ID, Constants::META_MAIL_SUBJECT, true );
						$raw_html = (string) get_post_meta( $log->ID, Constants::META_MAIL_RAW_HTML, true );

						$event_title = $event_id ? get_the_title( $event_id ) : '—';
						$badge_class = 'etm-badge';

						switch ( $status ) {
							case Constants::STATUS_SENT:
								$badge_class .= ' sent';
								$status_text  = __( 'Gesendet', 'event-tracker' );
								break;
							case Constants::STATUS_QUEUED:
								$badge_class .= ' queued';
								$status_text  = __( 'Geplant', 'event-tracker' );
								if ( $sched_ts ) {
									$status_text .= ': ' . wp_date( $df, $sched_ts, $tz );
								}
								break;
							case Constants::STATUS_ERROR:
								$badge_class .= ' error';
								$status_text  = __( 'Fehler', 'event-tracker' );
								break;
							case Constants::STATUS_STOPPED:
								$badge_class .= ' stopped';
								$status_text  = __( 'Gestoppt', 'event-tracker' );
								break;
							default:
								$status_text = $status;
						}
						?>
						<tr data-log-id="<?php echo esc_attr( $log->ID ); ?>" data-subject="<?php echo esc_attr( $subject ); ?>" data-event-id="<?php echo esc_attr( $event_id ); ?>">
							<td><?php echo esc_html( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', strtotime( $log->post_date_gmt ) ), 'Y-m-d H:i' ) ); ?></td>
							<td><?php echo esc_html( $event_title ); ?><br><small><?php echo esc_html( $subject ); ?></small></td>
							<td><span class="<?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $status_text ); ?></span></td>
							<td>
								<button type="button" class="etm-btn" data-action="recycle-mail"><?php esc_html_e( 'Wiederverwenden', 'event-tracker' ); ?></button>
								<?php if ( $is_rec && ! $stopped && $status === Constants::STATUS_QUEUED ) : ?>
									<button type="button" class="etm-btn danger" data-action="stop-job" data-log-id="<?php echo esc_attr( $log->ID ); ?>"><?php esc_html_e( 'Stoppen', 'event-tracker' ); ?></button>
								<?php else : ?>
									<button type="button" class="etm-btn danger" data-action="delete-log" data-log-id="<?php echo esc_attr( $log->ID ); ?>"><?php esc_html_e( 'Löschen', 'event-tracker' ); ?></button>
								<?php endif; ?>
								<textarea class="etm-raw-html" style="display:none"><?php echo esc_textarea( $raw_html ); ?></textarea>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr><td colspan="4"><?php esc_html_e( 'Noch keine Einträge.', 'event-tracker' ); ?></td></tr>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Enqueue Mailer JavaScript (aus Backup)
	 */
	private static function enqueue_mailer_script() {
		if ( self::$script_enqueued ) {
			return;
		}
		self::$script_enqueued = true;

		// Register script handle without file
		wp_register_script( 'et-mailer', false, [], '2.0.1', true );
		wp_enqueue_script( 'et-mailer' );

		// Add inline script BEFORE
		$js = <<<'JS'
(function(){
'use strict';
console.log('Event Tracker Mailer: Initializing (from backup pattern)...');
function closestWrap(el){while(el && el!==document && !(el.classList&&el.classList.contains('etm-wrap'))){el=el.parentElement;}return (el && el.classList && el.classList.contains('etm-wrap'))?el:null;}
function getEditorId(wrap){return wrap?wrap.getAttribute('data-editor')||'':'';}
function getHTML(wrap){var id=getEditorId(wrap);if(id&&window.tinymce&&tinymce.get(id)){return tinymce.get(id).getContent();}var ta=wrap.querySelector('#'+id);return ta?ta.value:'';}
function setHTML(wrap,html){var id=getEditorId(wrap);if(id&&window.tinymce&&tinymce.get(id)){tinymce.get(id).setContent(html||'');}else{var ta=wrap.querySelector('#'+id);if(ta)ta.value=html||'';}}
function ajax(wrap,action,fields){var fd=new FormData();fd.append('action',action);fd.append('nonce',wrap.getAttribute('data-nonce')||'');if(fields){Object.keys(fields).forEach(function(k){fd.append(k,fields[k]);});}return fetch(wrap.getAttribute('data-ajax'),{method:'POST',credentials:'include',body:fd}).then(function(r){return r.text();}).then(function(t){try{return JSON.parse(t);}catch(e){console.error('Event Tracker Mailer: JSON parse error:', e, t);throw new Error('Invalid JSON');}});}
function showMsg(wrap,msg,ok){var el=wrap.querySelector('.etm-msg');if(!el)return;el.textContent=msg||'';el.style.color=(ok===false)?'#7f1d1d':'#065f46';el.classList.add('show');setTimeout(function(){el.classList.remove('show');},5000);}
function setBusy(btn,b){if(!btn)return;if(b)btn.setAttribute('disabled','disabled');else btn.removeAttribute('disabled');}

// Toggle scheduling fields
document.addEventListener('change',function(ev){
  var r=ev.target;
  if(!r || r.name!=='etm-when') return;
  var wrap=closestWrap(r); if(!wrap) return;
  var at=wrap.querySelector('.etm-when-at');
  var it=wrap.querySelector('.etm-interval-min');
  var from=wrap.querySelector('.etm-interval-start');
  if(at) at.style.display = (r.value==='at') ? '' : 'none';
  if(it) it.style.display = (r.value==='until_start') ? '' : 'none';
  if(from) from.style.display = (r.value==='until_start') ? '' : 'none';
});

document.addEventListener('click',function(ev){
  var btn=ev.target.closest('.etm-wrap button[data-action]'); if(!btn) return;
  console.log('Event Tracker Mailer: Button clicked:', btn.getAttribute('data-action'));
  var wrap=closestWrap(btn); if(!wrap) return;
  var action=btn.getAttribute('data-action');
  var eventSel=wrap.querySelector('.etm-event-id');
  var testInput=wrap.querySelector('.etm-test-email');
  var subjectEl=wrap.querySelector('.etm-subject');
  var templateSel=wrap.querySelector('.etm-template-id');
  var html=getHTML(wrap)||'';
  var subject=subjectEl?(subjectEl.value||'').trim():'';
  var when=(wrap.querySelector('input[name="etm-when"]:checked')||{}).value||'now';
  var whenAtEl=wrap.querySelector('.etm-when-at');
  var whenAt=whenAtEl?(whenAtEl.value||'').trim():'';
  var intervalEl=wrap.querySelector('.etm-interval-min');
  var intervalMin=intervalEl?(intervalEl.value||'').trim():'';
  var fromEl=wrap.querySelector('.etm-interval-start');
  var intervalFrom=fromEl?(fromEl.value||'').trim():'';
  var ignoredateEl=wrap.querySelector('.etm-toggle-ignoredate');
  var ignoredate=(ignoredateEl&&ignoredateEl.checked)?'1':'0';

  if(action==='send-submit'){
    if(!eventSel||!parseInt(eventSel.value||'0',10)){showMsg(wrap,'Bitte Veranstaltung wählen',false);return;}
    if(!subject){showMsg(wrap,'Bitte Betreff eingeben',false);return;}
    if(!html||html.indexOf('{{URL}}')===-1){showMsg(wrap,'Mail muss {{URL}} enthalten',false);return;}
    if(when==='at' && !whenAt){showMsg(wrap,'Bitte Datum/Uhrzeit angeben',false);return;}
    if(when==='until_start' && !intervalMin){showMsg(wrap,'Bitte Intervall wählen',false);return;}
    if(!confirm('Wirklich abschicken/planen?')) return;
    setBusy(btn,true); showMsg(wrap,'Sende...',true);
    ajax(wrap,'et_send_mail',{event_id:eventSel.value,subject:subject,html:html,schedule:when,schedule_at:whenAt, schedule_interval:intervalMin, schedule_interval_start:intervalFrom, ignoredate:ignoredate})
    .then(function(data){
      if(data&&data.ok){
        showMsg(wrap,'Mail geplant/gesendet!',true);
        setTimeout(function(){location.reload();},1500);
      }else{
        showMsg(wrap,(data&&data.message)?data.message:'Fehler beim Versand',false);
      }
    })
    .catch(function(e){console.error('Event Tracker Mailer: Send error:',e);showMsg(wrap,'Netzwerkfehler',false);})
    .then(function(){setBusy(btn,false);});
  }
  else if(action==='send-test'){
    if(!eventSel||!parseInt(eventSel.value||'0',10)){showMsg(wrap,'Bitte Veranstaltung wählen',false);return;}
    if(!subject){showMsg(wrap,'Bitte Betreff eingeben',false);return;}
    if(!html){showMsg(wrap,'Bitte HTML eingeben',false);return;}
    var to=testInput?(testInput.value||'').trim():'';
    if(!to){showMsg(wrap,'Bitte Test-E-Mail eingeben',false);return;}
    if(!confirm('Testmail senden?')) return;
    setBusy(btn,true); showMsg(wrap,'Sende Testmail...',true);
    ajax(wrap,'et_test_mail',{event_id:eventSel.value,subject:subject,html:html,email:to})
    .then(function(data){
      if(data&&data.ok){showMsg(wrap,'Testmail gesendet',true);}
      else{showMsg(wrap,(data&&data.message)?data.message:'Fehler',false);}
    })
    .catch(function(e){console.error('Event Tracker Mailer: Test error:',e);showMsg(wrap,'Netzwerkfehler',false);})
    .then(function(){setBusy(btn,false);});
  }
  else if(action==='delete-log'){
    if(!confirm('Wirklich löschen?')) return;
    var logId=btn.getAttribute('data-log-id');
    var row=btn.closest('tr');
    ajax(wrap,'et_delete_mail_log',{log_id:logId})
    .then(function(data){
      if(data&&data.ok){
        if(row)row.remove();
        showMsg(wrap,'Gelöscht',true);
      }else{
        showMsg(wrap,'Löschen fehlgeschlagen',false);
      }
    })
    .catch(function(){showMsg(wrap,'Netzwerkfehler',false);});
  }
  else if(action==='stop-job'){
    if(!confirm('Wirklich stoppen?')) return;
    var logId=btn.getAttribute('data-log-id');
    ajax(wrap,'et_stop_mail_job',{log_id:logId})
    .then(function(data){
      if(data&&data.ok){
        showMsg(wrap,'Job gestoppt',true);
        setTimeout(function(){location.reload();},1000);
      }else{
        showMsg(wrap,'Konnte nicht gestoppt werden',false);
      }
    })
    .catch(function(){showMsg(wrap,'Netzwerkfehler',false);});
  }
  else if(action==='load-template'){
    var tplId=templateSel?(parseInt(templateSel.value||'0',10)):0;
    if(!tplId){showMsg(wrap,'Bitte Vorlage wählen',false);return;}
    setBusy(btn,true);showMsg(wrap,'Lade Vorlage...',true);
    ajax(wrap,'et_get_template',{template_id:tplId})
    .then(function(data){
      if(data&&data.data&&data.data.html){
        setHTML(wrap,data.data.html);
        if(subjectEl&&data.data.title)subjectEl.value=data.data.title;
        showMsg(wrap,'Vorlage geladen',true);
      }else{
        showMsg(wrap,'Fehler beim Laden',false);
      }
    })
    .catch(function(e){console.error('Load template error:',e);showMsg(wrap,'Netzwerkfehler',false);})
    .then(function(){setBusy(btn,false);});
  }
  else if(action==='save-template'){
    if(!html){showMsg(wrap,'Bitte HTML-Inhalt eingeben',false);return;}
    var title=prompt('Vorlagen-Titel:',subject||'Neue Vorlage');
    if(!title)return;
    setBusy(btn,true);showMsg(wrap,'Speichere Vorlage...',true);
    ajax(wrap,'et_save_template',{title:title,html:html})
    .then(function(data){
      if(data&&data.data){
        showMsg(wrap,'Vorlage gespeichert!',true);
        setTimeout(function(){location.reload();},1500);
      }else{
        showMsg(wrap,(data&&data.message)?data.message:'Fehler beim Speichern',false);
      }
    })
    .catch(function(e){console.error('Save template error:',e);showMsg(wrap,'Netzwerkfehler',false);})
    .then(function(){setBusy(btn,false);});
  }
  else if(action==='delete-template'){
    var tplId=templateSel?(parseInt(templateSel.value||'0',10)):0;
    if(!tplId){showMsg(wrap,'Bitte Vorlage wählen',false);return;}
    if(!confirm('Vorlage wirklich löschen?'))return;
    setBusy(btn,true);showMsg(wrap,'Lösche Vorlage...',true);
    ajax(wrap,'et_delete_template',{template_id:tplId})
    .then(function(data){
      if(data&&data.data){
        showMsg(wrap,'Vorlage gelöscht',true);
        setTimeout(function(){location.reload();},1500);
      }else{
        showMsg(wrap,(data&&data.message)?data.message:'Fehler beim Löschen',false);
      }
    })
    .catch(function(e){console.error('Delete template error:',e);showMsg(wrap,'Netzwerkfehler',false);})
    .then(function(){setBusy(btn,false);});
  }
  else if(action==='recycle-mail'){
    var row=btn.closest('tr');
    if(!row)return;
    var rawHtmlEl=row.querySelector('.etm-raw-html');
    var recycleHtml=rawHtmlEl?rawHtmlEl.value:'';
    var recycleSubject=row.getAttribute('data-subject')||'';
    var recycleEvent=row.getAttribute('data-event-id')||'';
    if(!recycleHtml){showMsg(wrap,'Keine Mail-Daten gefunden',false);return;}
    setHTML(wrap,recycleHtml);
    if(subjectEl)subjectEl.value=recycleSubject;
    if(eventSel&&recycleEvent)eventSel.value=recycleEvent;
    showMsg(wrap,'Mail wiederverwendet - bitte überprüfen und anpassen',true);
    window.scrollTo({top:0,behavior:'smooth'});
  }
});
console.log('Event Tracker Mailer: Event listeners registered');
}());
JS;

		wp_add_inline_script( 'et-mailer', $js );
	}
}
