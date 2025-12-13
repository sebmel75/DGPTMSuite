<?php
/**
 * Event Tracker AJAX Handler
 *
 * @package EventTracker\Ajax
 * @since 2.0.0
 */

namespace EventTracker\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use EventTracker\Core\Constants;
use EventTracker\Core\Helpers;

/**
 * AJAX Handler Class
 */
class Handler {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Event List & Form AJAX
		add_action( 'wp_ajax_et_fetch_event_list', [ $this, 'fetch_event_list' ] );
		add_action( 'wp_ajax_nopriv_et_fetch_event_list', [ $this, 'fetch_event_list' ] );
		add_action( 'wp_ajax_et_fetch_event_form', [ $this, 'fetch_event_form' ] );
		add_action( 'wp_ajax_nopriv_et_fetch_event_form', [ $this, 'fetch_event_form' ] );
		add_action( 'wp_ajax_et_delete_event', [ $this, 'delete_event' ] );

		// Template Management
		add_action( 'wp_ajax_et_get_template', [ $this, 'get_template' ] );
		add_action( 'wp_ajax_et_save_template', [ $this, 'save_template' ] );
		add_action( 'wp_ajax_et_delete_template', [ $this, 'delete_template' ] );

		// Mail System
		add_action( 'wp_ajax_et_send_mail', [ $this, 'send_mail' ] );
		add_action( 'wp_ajax_et_test_mail', [ $this, 'test_mail' ] );
		add_action( 'wp_ajax_et_delete_mail_log', [ $this, 'delete_mail_log' ] );
		add_action( 'wp_ajax_et_stop_mail_job', [ $this, 'stop_mail_job' ] );
	}

	/**
	 * Fetch Event List (AJAX)
	 */
	public function fetch_event_list() {
		check_ajax_referer( 'et_panels', 'nonce' );

		$query = new \WP_Query(
			[
				'post_type'      => Constants::CPT,
				'posts_per_page' => -1,
				'orderby'        => 'meta_value_num',
				'meta_key'       => Constants::META_START_TS,
				'order'          => 'DESC',
			]
		);

		if ( ! $query->have_posts() ) {
			wp_send_json_success(
				[
					'html' => '<p>' . esc_html__( 'Keine Veranstaltungen vorhanden.', 'event-tracker' ) . '</p>',
				]
			);
		}

		ob_start();
		$this->render_event_list( $query );
		$html = ob_get_clean();

		wp_send_json_success( [ 'html' => $html ] );
	}

	/**
	 * Render Event List Table
	 *
	 * @param \WP_Query $query Event query.
	 */
	private function render_event_list( $query ) {
		$tz  = wp_timezone();
		$now = time();
		$can_edit = is_user_logged_in();
		$show_action_link = true;
		$df = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		?>
		<style>
			.et-event-list{width:100%;border-collapse:collapse;margin-top:12px}
			.et-event-list th,.et-event-list td{padding:10px;text-align:left;border-bottom:1px solid #e5e7eb}
			.et-event-list th{background:#f9fafb;font-weight:600}
			.et-badge{display:inline-block;padding:4px 8px;border-radius:6px;font-size:.85rem;font-weight:500}
			.et-badge.active{background:#d1fae5;color:#065f46}
			.et-badge.upcoming{background:#fef3c7;color:#92400e}
			.et-badge.expired{background:#fee2e2;color:#991b1b}
			.et-actions{display:flex;gap:8px;flex-wrap:wrap}
			.et-actions a,.et-actions button{padding:6px 12px;border-radius:6px;border:1px solid #e5e7eb;background:#fff;cursor:pointer;text-decoration:none;color:#374151;font-size:.9rem}
			.et-actions button:hover{background:#f3f4f6}
			@media (max-width:768px){
				.et-event-list thead{display:none}
				.et-event-list tr{display:block;margin-bottom:16px;border:1px solid #e5e7eb;border-radius:8px;padding:12px}
				.et-event-list td{display:block;text-align:left;padding:6px 0;border:none}
				.et-event-list td:before{content:attr(data-label);font-weight:600;display:inline-block;width:100px}
			}
		</style>
		<table class="et-event-list">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Veranstaltungsname', 'event-tracker' ); ?></th>
					<th><?php esc_html_e( 'Gültig ab', 'event-tracker' ); ?></th>
					<th><?php esc_html_e( 'Gültig bis', 'event-tracker' ); ?></th>
					<th><?php esc_html_e( 'Status', 'event-tracker' ); ?></th>
					<?php if ( $show_action_link ) : ?>
						<th><?php esc_html_e( 'Aktionen', 'event-tracker' ); ?></th>
					<?php endif; ?>
				</tr>
			</thead>
			<tbody>
			<?php
			while ( $query->have_posts() ) :
				$query->the_post();
				$post_id = get_the_ID();
				$name    = get_the_title();
				$start   = (int) get_post_meta( $post_id, Constants::META_START_TS, true );
				$end     = (int) get_post_meta( $post_id, Constants::META_END_TS, true );
				$target  = get_post_meta( $post_id, Constants::META_REDIRECT_URL, true );

				// Determine status
				$is_valid = Helpers::is_event_valid( $post_id, $now );
				$has_future = Helpers::has_future_dates( $post_id, $now );

				if ( $is_valid ) {
					$badge_class = 'et-badge active';
					$badge_text  = __( 'Aktiv', 'event-tracker' );
				} elseif ( $has_future ) {
					// Event has future dates (mark as active instead of upcoming)
					$badge_class = 'et-badge active';
					$badge_text  = __( 'Aktiv', 'event-tracker' );
				} elseif ( $start && $start > $now ) {
					$badge_class = 'et-badge upcoming';
					$badge_text  = __( 'Bevorstehend', 'event-tracker' );
				} else {
					$badge_class = 'et-badge expired';
					$badge_text  = __( 'Abgelaufen', 'event-tracker' );
				}

				// Event link
				$link = home_url( '/eventtracker?id=' . $post_id );
				?>
				<tr>
					<td data-label="<?php esc_attr_e( 'Veranstaltungsname', 'event-tracker' ); ?>"><?php echo esc_html( $name ); ?></td>
					<td data-label="<?php esc_attr_e( 'Gültig ab', 'event-tracker' ); ?>"><?php echo $start ? esc_html( wp_date( $df, $start, $tz ) ) : '—'; ?></td>
					<td data-label="<?php esc_attr_e( 'Gültig bis', 'event-tracker' ); ?>"><?php echo $end ? esc_html( wp_date( $df, $end, $tz ) ) : '—'; ?></td>
					<td data-label="<?php esc_attr_e( 'Status', 'event-tracker' ); ?>"><span class="<?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $badge_text ); ?></span></td>
					<?php if ( $show_action_link ) : ?>
						<td data-label="<?php esc_attr_e( 'Aktionen', 'event-tracker' ); ?>" class="et-actions">
							<?php if ( $target ) : ?>
								<a href="<?php echo esc_url( $link ); ?>"><?php esc_html_e( 'Zum Event / prüfen', 'event-tracker' ); ?></a>
							<?php else : ?>
								<em><?php esc_html_e( 'Keine Ziel-URL hinterlegt', 'event-tracker' ); ?></em>
							<?php endif; ?>
							<?php if ( $can_edit ) : ?>
								<button type="button" class="et-btn" data-action="edit" data-event-id="<?php echo esc_attr( $post_id ); ?>"><?php esc_html_e( 'Bearbeiten', 'event-tracker' ); ?></button>
							<?php endif; ?>
						</td>
					<?php endif; ?>
				</tr>
				<?php
			endwhile;
			wp_reset_postdata();
			?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Fetch Event Form (AJAX)
	 */
	public function fetch_event_form() {
		check_ajax_referer( 'et_panels', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error(
				[
					'message' => __( 'Sie müssen eingeloggt sein.', 'event-tracker' ),
				]
			);
		}

		$event_id = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;

		ob_start();
		$this->render_event_form( $event_id );
		$html = ob_get_clean();

		wp_send_json_success( [ 'html' => $html ] );
	}

	/**
	 * Render Event Form
	 *
	 * @param int $event_id Event ID (0 for new event).
	 */
	private function render_event_form( $event_id = 0 ) {
		$tz      = wp_timezone_string();
		$is_edit = $event_id > 0;
		$title   = '';
		$start_ts = 0;
		$end_ts   = 0;
		$url      = '';
		$zoho     = '';
		$rec      = '';
		$if_on    = false;
		$if_url   = '';

		if ( $is_edit && get_post_type( $event_id ) === Constants::CPT ) {
			$title    = get_the_title( $event_id );
			$start_ts = (int) get_post_meta( $event_id, Constants::META_START_TS, true );
			$end_ts   = (int) get_post_meta( $event_id, Constants::META_END_TS, true );
			$url      = (string) get_post_meta( $event_id, Constants::META_REDIRECT_URL, true );
			$zoho     = (string) get_post_meta( $event_id, Constants::META_ZOHO_ID, true );
			$rec      = (string) get_post_meta( $event_id, Constants::META_RECORDING_URL, true );
			$if_on    = (string) get_post_meta( $event_id, Constants::META_IFRAME_ENABLE, true ) === '1';
			$if_url   = (string) get_post_meta( $event_id, Constants::META_IFRAME_URL, true );
		}

		$start_val = Helpers::format_datetime_local( $start_ts );
		$end_val   = Helpers::format_datetime_local( $end_ts );
		$action    = esc_url( add_query_arg( [] ) );
		?>
		<style>
			.et-form{margin-top:12px;border:1px solid #e5e7eb;padding:16px;border-radius:12px;max-width:820px}
			.et-form h3{margin:0 0 12px 0}
			.et-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
			.et-grid .full{grid-column:1/-1}
			.et-form input[type="text"],.et-form input[type="url"],.et-form input[type="datetime-local"]{width:100%;padding:8px;border:1px solid #d1d5db;border-radius:6px}
			.et-form label{display:block;margin-bottom:6px}
			.et-hint{font-size:.9rem;color:#6b7280;margin-top:4px}
			.et-inline{display:flex;align-items:center;gap:8px}
			@media (max-width:640px){.et-grid{grid-template-columns:1fr}}
		</style>
		<form class="et-form" method="post" action="<?php echo $action; ?>">
			<h3><?php echo $is_edit ? esc_html__( 'Veranstaltung bearbeiten', 'event-tracker' ) : esc_html__( 'Neue Veranstaltung anlegen', 'event-tracker' ); ?></h3>
			<?php wp_nonce_field( 'et_front_save', 'et_front_nonce' ); ?>
			<input type="hidden" name="et_save_event" value="1" />
			<input type="hidden" name="et_event_id" value="<?php echo esc_attr( $event_id ); ?>" />
			<div class="et-grid">
				<div class="full">
					<label><strong><?php esc_html_e( 'Titel (Veranstaltungsname)', 'event-tracker' ); ?></strong></label>
					<input type="text" name="et_title" required value="<?php echo esc_attr( $title ); ?>" />
				</div>
				<div>
					<label><strong><?php esc_html_e( 'Gültig ab (Datum & Uhrzeit)', 'event-tracker' ); ?></strong></label>
					<input type="datetime-local" name="et_start" required value="<?php echo esc_attr( $start_val ); ?>" />
					<div class="et-hint"><?php echo esc_html( sprintf( __( 'Zeitzone: %s', 'event-tracker' ), $tz ) ); ?></div>
				</div>
				<div>
					<label><strong><?php esc_html_e( 'Gültig bis (Datum & Uhrzeit)', 'event-tracker' ); ?></strong></label>
					<input type="datetime-local" name="et_end" required value="<?php echo esc_attr( $end_val ); ?>" />
				</div>
				<div class="full">
					<label><strong><?php esc_html_e( 'Weiterleitungs-URL', 'event-tracker' ); ?></strong></label>
					<input type="url" name="et_url" placeholder="https://example.com/ziel" required value="<?php echo esc_attr( $url ); ?>" />
				</div>
				<div class="full">
					<label><strong><?php esc_html_e( 'Zoho-ID (optional, für Mail/Webhook)', 'event-tracker' ); ?></strong></label>
					<input type="text" name="et_zoho_id" value="<?php echo esc_attr( $zoho ); ?>" />
				</div>
				<div class="full">
					<label><strong><?php esc_html_e( 'Aufzeichnungs-URL (optional)', 'event-tracker' ); ?></strong></label>
					<input type="url" name="et_recording_url" placeholder="https://example.com/aufzeichnung" value="<?php echo esc_attr( $rec ); ?>" />
				</div>
				<div class="full">
					<label class="et-inline">
						<input type="checkbox" name="et_iframe_enable" value="1" <?php checked( $if_on ); ?> />
						<strong><?php esc_html_e( 'Live-Stream im Iframe anzeigen (statt Redirect)', 'event-tracker' ); ?></strong>
					</label>
					<input type="url" name="et_iframe_url" placeholder="https://teams.microsoft.com/..." value="<?php echo esc_attr( $if_url ); ?>" />
					<div class="et-hint"><?php esc_html_e( 'Desktop: Popup, Smartphone: neues Fenster', 'event-tracker' ); ?></div>
				</div>

				<div class="full">
					<label><strong><?php esc_html_e( 'Mehrtägige Events (zusätzliche Termine)', 'event-tracker' ); ?></strong></label>
					<div class="et-hint"><?php esc_html_e( 'Für Events die an mehreren Tagen mit demselben Link stattfinden.', 'event-tracker' ); ?></div>
					<div class="et-additional-dates">
						<?php
						$additional = get_post_meta( $event_id, Constants::META_ADDITIONAL_DATES, true );
						if ( is_array( $additional ) && ! empty( $additional ) ) :
							foreach ( $additional as $idx => $range ) :
								$range_start = isset( $range['start'] ) ? Helpers::format_datetime_local( $range['start'] ) : '';
								$range_end   = isset( $range['end'] ) ? Helpers::format_datetime_local( $range['end'] ) : '';
								?>
								<div class="et-date-range" style="display:grid;grid-template-columns:1fr 1fr auto;gap:8px;margin-bottom:8px;">
									<input type="datetime-local" name="et_additional_start[]" value="<?php echo esc_attr( $range_start ); ?>" />
									<input type="datetime-local" name="et_additional_end[]" value="<?php echo esc_attr( $range_end ); ?>" />
									<button type="button" class="et-remove-date" style="padding:4px 8px;">×</button>
								</div>
							<?php endforeach; endif; ?>
					</div>
					<button type="button" class="et-add-date" style="margin-top:8px;padding:6px 12px;border:1px solid #d1d5db;background:#f9fafb;border-radius:6px;cursor:pointer;"><?php esc_html_e( '+ Weiteren Termin hinzufügen', 'event-tracker' ); ?></button>
				</div>
			</div>
			<p><button type="submit" class="button button-primary"><?php echo $is_edit ? esc_html__( 'Änderungen speichern', 'event-tracker' ) : esc_html__( 'Veranstaltung erstellen', 'event-tracker' ); ?></button></p>
		</form>
		<script>
		(function() {
			var form = document.querySelector('.et-form');
			if (!form) return;

			// Add date range
			form.addEventListener('click', function(e) {
				if (e.target.classList.contains('et-add-date')) {
					var container = form.querySelector('.et-additional-dates');
					var div = document.createElement('div');
					div.className = 'et-date-range';
					div.style.cssText = 'display:grid;grid-template-columns:1fr 1fr auto;gap:8px;margin-bottom:8px;';
					div.innerHTML = '<input type="datetime-local" name="et_additional_start[]" /><input type="datetime-local" name="et_additional_end[]" /><button type="button" class="et-remove-date" style="padding:4px 8px;">×</button>';
					container.appendChild(div);
				}

				// Remove date range
				if (e.target.classList.contains('et-remove-date')) {
					e.target.closest('.et-date-range').remove();
				}
			});
		})();
		</script>
		<?php
	}

	/**
	 * Mail/Template Handlers
	 */

	/**
	 * Send Mail (AJAX)
	 */
	public function send_mail() {
		check_ajax_referer( 'et_mailer', 'nonce' );

		if ( ! Helpers::user_has_access() ) {
			wp_send_json_error( [ 'message' => __( 'Keine Berechtigung.', 'event-tracker' ) ] );
		}

		$event_id  = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;
		$subject   = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$html      = isset( $_POST['html'] ) ? wp_kses_post( wp_unslash( $_POST['html'] ) ) : '';
		$schedule  = isset( $_POST['schedule'] ) ? sanitize_text_field( wp_unslash( $_POST['schedule'] ) ) : 'now';
		$sched_at  = isset( $_POST['schedule_at'] ) ? sanitize_text_field( wp_unslash( $_POST['schedule_at'] ) ) : '';
		$interval  = isset( $_POST['schedule_interval'] ) ? absint( $_POST['schedule_interval'] ) : 0;
		$from_ts   = isset( $_POST['schedule_interval_start'] ) ? sanitize_text_field( wp_unslash( $_POST['schedule_interval_start'] ) ) : '';
		$ignoredate = isset( $_POST['ignoredate'] ) ? sanitize_text_field( wp_unslash( $_POST['ignoredate'] ) ) : '0';

		// Get mailer instance
		$plugin = \event_tracker_init();
		$mailer = $plugin->get_component( 'mailer' );

		if ( ! $mailer ) {
			wp_send_json_error( [ 'message' => __( 'Mailer nicht verfügbar.', 'event-tracker' ) ] );
		}

		$options = [
			'schedule_at'   => $sched_at,
			'interval'      => $interval,
			'interval_from' => $from_ts,
			'ignoredate'    => $ignoredate,
		];

		$result = $mailer->send_mail( $event_id, $subject, $html, $schedule, $options );

		if ( $result['ok'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * Send Test Mail (AJAX)
	 */
	public function test_mail() {
		check_ajax_referer( 'et_mailer', 'nonce' );

		if ( ! Helpers::user_has_access() ) {
			wp_send_json_error( [ 'message' => __( 'Keine Berechtigung.', 'event-tracker' ) ] );
		}

		$event_id = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;
		$subject  = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$html     = isset( $_POST['html'] ) ? wp_kses_post( wp_unslash( $_POST['html'] ) ) : '';
		$email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

		// Get mailer instance
		$plugin = \event_tracker_init();
		$mailer = $plugin->get_component( 'mailer' );

		if ( ! $mailer ) {
			wp_send_json_error( [ 'message' => __( 'Mailer nicht verfügbar.', 'event-tracker' ) ] );
		}

		$result = $mailer->send_test_mail( $email, $subject, $html, $event_id );

		if ( $result['ok'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * Delete Mail Log (AJAX)
	 */
	public function delete_mail_log() {
		check_ajax_referer( 'et_mailer', 'nonce' );

		if ( ! Helpers::user_has_access() ) {
			wp_send_json_error( [ 'message' => __( 'Keine Berechtigung.', 'event-tracker' ) ] );
		}

		$log_id = isset( $_POST['log_id'] ) ? absint( $_POST['log_id'] ) : 0;

		if ( ! $log_id || get_post_type( $log_id ) !== Constants::CPT_MAIL_LOG ) {
			wp_send_json_error( [ 'message' => __( 'Ungültiger Log-Eintrag.', 'event-tracker' ) ] );
		}

		Helpers::begin_cap_override();
		$deleted = wp_delete_post( $log_id, true );
		Helpers::end_cap_override();

		if ( $deleted ) {
			wp_send_json_success( [ 'message' => __( 'Eintrag gelöscht.', 'event-tracker' ) ] );
		} else {
			wp_send_json_error( [ 'message' => __( 'Löschen fehlgeschlagen.', 'event-tracker' ) ] );
		}
	}

	/**
	 * Stop Mail Job (AJAX)
	 */
	public function stop_mail_job() {
		check_ajax_referer( 'et_mailer', 'nonce' );

		if ( ! Helpers::user_has_access() ) {
			wp_send_json_error( [ 'message' => __( 'Keine Berechtigung.', 'event-tracker' ) ] );
		}

		$log_id = isset( $_POST['log_id'] ) ? absint( $_POST['log_id'] ) : 0;

		// Get mailer instance
		$plugin = \event_tracker_init();
		$mailer = $plugin->get_component( 'mailer' );

		if ( ! $mailer ) {
			wp_send_json_error( [ 'message' => __( 'Mailer nicht verfügbar.', 'event-tracker' ) ] );
		}

		$success = $mailer->stop_recurring_mail( $log_id );

		if ( $success ) {
			wp_send_json_success( [ 'message' => __( 'Job gestoppt.', 'event-tracker' ) ] );
		} else {
			wp_send_json_error( [ 'message' => __( 'Konnte nicht gestoppt werden.', 'event-tracker' ) ] );
		}
	}

	/**
	 * Delete Event (AJAX)
	 */
	public function delete_event() {
		check_ajax_referer( 'et_panels', 'nonce' );

		if ( ! current_user_can( 'delete_posts' ) ) {
			wp_send_json_error( [ 'message' => __( 'Keine Berechtigung.', 'event-tracker' ) ] );
		}

		$event_id = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;

		if ( ! $event_id ) {
			wp_send_json_error( [ 'message' => __( 'Keine Event-ID angegeben.', 'event-tracker' ) ] );
		}

		// Verify it's an event post
		if ( get_post_type( $event_id ) !== Constants::CPT ) {
			wp_send_json_error( [ 'message' => __( 'Ungültiges Event.', 'event-tracker' ) ] );
		}

		// Temporarily elevate caps for delete
		Helpers::begin_cap_override();
		$deleted = wp_delete_post( $event_id, true );
		Helpers::end_cap_override();

		if ( $deleted ) {
			wp_send_json_success( [ 'message' => __( 'Event gelöscht.', 'event-tracker' ) ] );
		} else {
			wp_send_json_error( [ 'message' => __( 'Fehler beim Löschen.', 'event-tracker' ) ] );
		}
	}

	/**
	 * Template Handlers - Save as Draft Feature
	 */

	/**
	 * Get Template (AJAX)
	 */
	public function get_template() {
		check_ajax_referer( 'et_mailer', 'nonce' );

		if ( ! Helpers::user_has_access() ) {
			wp_send_json_error( [ 'message' => __( 'Keine Berechtigung.', 'event-tracker' ) ] );
		}

		$template_id = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0;

		if ( ! $template_id || get_post_type( $template_id ) !== Constants::CPT_MAIL_TPL ) {
			wp_send_json_error( [ 'message' => __( 'Ungültige Vorlage.', 'event-tracker' ) ] );
		}

		$post = get_post( $template_id );
		if ( ! $post ) {
			wp_send_json_error( [ 'message' => __( 'Vorlage nicht gefunden.', 'event-tracker' ) ] );
		}

		wp_send_json_success(
			[
				'title' => $post->post_title,
				'html'  => $post->post_content,
			]
		);
	}

	/**
	 * Save Template (AJAX)
	 */
	public function save_template() {
		check_ajax_referer( 'et_mailer', 'nonce' );

		if ( ! Helpers::user_has_access() ) {
			wp_send_json_error( [ 'message' => __( 'Keine Berechtigung.', 'event-tracker' ) ] );
		}

		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$html  = isset( $_POST['html'] ) ? wp_kses_post( wp_unslash( $_POST['html'] ) ) : '';

		if ( ! $title ) {
			wp_send_json_error( [ 'message' => __( 'Titel fehlt.', 'event-tracker' ) ] );
		}

		Helpers::begin_cap_override();

		$template_id = wp_insert_post(
			[
				'post_type'    => Constants::CPT_MAIL_TPL,
				'post_title'   => $title,
				'post_content' => $html,
				'post_status'  => 'publish',
				'post_author'  => get_current_user_id(),
			]
		);

		Helpers::end_cap_override();

		if ( is_wp_error( $template_id ) || ! $template_id ) {
			wp_send_json_error( [ 'message' => __( 'Fehler beim Speichern.', 'event-tracker' ) ] );
		}

		wp_send_json_success(
			[
				'message'     => __( 'Vorlage gespeichert.', 'event-tracker' ),
				'template_id' => $template_id,
			]
		);
	}

	/**
	 * Delete Template (AJAX)
	 */
	public function delete_template() {
		check_ajax_referer( 'et_mailer', 'nonce' );

		if ( ! Helpers::user_has_access() ) {
			wp_send_json_error( [ 'message' => __( 'Keine Berechtigung.', 'event-tracker' ) ] );
		}

		$template_id = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0;

		if ( ! $template_id || get_post_type( $template_id ) !== Constants::CPT_MAIL_TPL ) {
			wp_send_json_error( [ 'message' => __( 'Ungültige Vorlage.', 'event-tracker' ) ] );
		}

		Helpers::begin_cap_override();
		$deleted = wp_delete_post( $template_id, true );
		Helpers::end_cap_override();

		if ( $deleted ) {
			wp_send_json_success( [ 'message' => __( 'Vorlage gelöscht.', 'event-tracker' ) ] );
		} else {
			wp_send_json_error( [ 'message' => __( 'Löschen fehlgeschlagen.', 'event-tracker' ) ] );
		}
	}
}
