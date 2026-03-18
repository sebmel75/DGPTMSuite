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
use EventTracker\ZohoMeeting\Client as ZohoMeetingClient;

/**
 * AJAX Handler Class
 */
class Handler {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Event List & Form AJAX (nur eingeloggte User)
		add_action( 'wp_ajax_et_fetch_event_list', [ $this, 'fetch_event_list' ] );
		add_action( 'wp_ajax_et_fetch_event_form', [ $this, 'fetch_event_form' ] );
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

		// Zoho Meeting
		add_action( 'wp_ajax_et_zm_create_webinar', [ $this, 'zm_create_webinar' ] );
		add_action( 'wp_ajax_et_zm_get_links', [ $this, 'zm_get_links' ] );
		add_action( 'wp_ajax_et_zm_add_cohosts', [ $this, 'zm_add_cohosts' ] );
		add_action( 'wp_ajax_et_zm_get_recording', [ $this, 'zm_get_recording' ] );
		add_action( 'wp_ajax_et_zm_test_connection', [ $this, 'zm_test_connection' ] );
		add_action( 'wp_ajax_et_zm_start_webinar', [ $this, 'zm_start_webinar' ] );
		add_action( 'wp_ajax_et_zm_search_users', [ $this, 'zm_search_users' ] );
		add_action( 'wp_ajax_et_zm_delete_webinar', [ $this, 'zm_delete_webinar' ] );
	}

	/**
	 * Fetch Event List (AJAX)
	 *
	 * Transient-Caching: 5 Minuten TTL, invalidiert bei save_post für et_event.
	 */
	public function fetch_event_list() {
		check_ajax_referer( 'et_panels', 'nonce' );

		// Transient-Cache prüfen
		$cache_key = 'dgptm_events_list';
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			wp_send_json_success( [ 'html' => $cached ] );
		}

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
			$empty_html = '<p>' . esc_html__( 'Keine Veranstaltungen vorhanden.', 'event-tracker' ) . '</p>';
			set_transient( $cache_key, $empty_html, 5 * MINUTE_IN_SECONDS );
			wp_send_json_success(
				[
					'html' => $empty_html,
				]
			);
		}

		ob_start();
		$this->render_event_list( $query );
		$html = ob_get_clean();

		// Ergebnis cachen (5 Minuten TTL)
		set_transient( $cache_key, $html, 5 * MINUTE_IN_SECONDS );

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
		$can_edit = Helpers::user_has_access();
		$show_action_link = true;
		$df = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		?>
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

		if ( ! Helpers::user_has_access() ) {
			wp_send_json_error(
				[
					'message' => __( 'Keine Berechtigung.', 'event-tracker' ),
				]
			);
		}

		$event_id = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;

		// Validate event exists if ID is provided
		if ( $event_id && get_post_type( $event_id ) !== Constants::CPT ) {
			wp_send_json_error(
				[
					'message' => __( 'Veranstaltung nicht gefunden.', 'event-tracker' ),
				]
			);
		}

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
								<div class="et-date-range">
									<input type="datetime-local" name="et_additional_start[]" value="<?php echo esc_attr( $range_start ); ?>" />
									<input type="datetime-local" name="et_additional_end[]" value="<?php echo esc_attr( $range_end ); ?>" />
									<button type="button" class="et-remove-date">×</button>
								</div>
							<?php endforeach; endif; ?>
					</div>
					<button type="button" class="et-add-date"><?php esc_html_e( '+ Weiteren Termin hinzufuegen', 'event-tracker' ); ?></button>
				</div>
			</div>
			<p><button type="submit" class="button button-primary"><?php echo $is_edit ? esc_html__( 'Änderungen speichern', 'event-tracker' ) : esc_html__( 'Veranstaltung erstellen', 'event-tracker' ); ?></button></p>
		</form>

		<?php if ( $is_edit ) : ?>
			<?php $this->render_zoho_meeting_panel( $event_id ); ?>
		<?php endif; ?>

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
					div.innerHTML = '<input type="datetime-local" name="et_additional_start[]" /><input type="datetime-local" name="et_additional_end[]" /><button type="button" class="et-remove-date">×</button>';
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

		if ( ! Helpers::user_has_access() ) {
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

	/* =========================================================================
	 * Zoho Meeting Panel Rendering
	 * ======================================================================= */

	/**
	 * Render Zoho Meeting Panel (collapsible, below event form)
	 *
	 * @param int $event_id Event ID.
	 */
	private function render_zoho_meeting_panel( $event_id ) {
		$zm_key       = get_post_meta( $event_id, Constants::META_ZM_KEY, true );
		$zm_start_url = get_post_meta( $event_id, Constants::META_ZM_START_URL, true );
		$zm_join_url  = get_post_meta( $event_id, Constants::META_ZM_JOIN_URL, true );
		$zm_rec_url   = get_post_meta( $event_id, Constants::META_ZM_RECORDING_URL, true );
		$zm_cohosts   = get_post_meta( $event_id, Constants::META_ZM_COHOSTS, true );
		$zm_status    = get_post_meta( $event_id, Constants::META_ZM_STATUS, true );

		$cohosts_arr = $zm_cohosts ? json_decode( $zm_cohosts, true ) : [];
		if ( ! is_array( $cohosts_arr ) ) {
			$cohosts_arr = [];
		}

		$status_labels = [
			''        => __( 'Nicht verknuepft', 'event-tracker' ),
			'created' => __( 'Erstellt', 'event-tracker' ),
			'started' => __( 'Gestartet', 'event-tracker' ),
			'ended'   => __( 'Beendet', 'event-tracker' ),
		];
		$status_label = isset( $status_labels[ $zm_status ] ) ? $status_labels[ $zm_status ] : $zm_status;
		?>
		<div class="et-zm-panel open" data-event-id="<?php echo esc_attr( $event_id ); ?>">
			<button type="button" class="et-zm-toggle">
				<span class="et-zm-arrow">&#9654;</span>
				<?php esc_html_e( 'Zoho Meeting', 'event-tracker' ); ?>
				<span class="et-zm-status et-zm-status--<?php echo esc_attr( $zm_status ?: 'none' ); ?>"><?php echo esc_html( $status_label ); ?></span>
			</button>
			<div class="et-zm-body">
				<div class="et-zm-msg" id="et-zm-msg"></div>

				<?php if ( ! $zm_key ) : ?>
					<!-- No webinar linked yet -->
					<div class="et-zm-section">
						<p><?php esc_html_e( 'Noch kein Webinar verknuepft.', 'event-tracker' ); ?></p>
						<button type="button" class="et-zm-btn primary" data-zm-action="create"><?php esc_html_e( 'Webinar anlegen', 'event-tracker' ); ?></button>
					</div>
				<?php else : ?>
					<!-- Links Section -->
					<div class="et-zm-section">
						<h4><?php esc_html_e( 'Links', 'event-tracker' ); ?></h4>
						<div class="et-zm-link-row">
							<strong class="et-zm-label"><?php esc_html_e( 'Start-Link:', 'event-tracker' ); ?></strong>
							<input type="text" id="et-zm-start-url" value="<?php echo esc_attr( $zm_start_url ); ?>" readonly />
							<button type="button" class="et-zm-btn" data-zm-action="copy" data-zm-target="et-zm-start-url"><?php esc_html_e( 'Kopieren', 'event-tracker' ); ?></button>
						</div>
						<div class="et-zm-link-row">
							<strong class="et-zm-label"><?php esc_html_e( 'Zugangs-Link:', 'event-tracker' ); ?></strong>
							<input type="text" id="et-zm-join-url" value="<?php echo esc_attr( $zm_join_url ); ?>" readonly />
							<button type="button" class="et-zm-btn" data-zm-action="copy" data-zm-target="et-zm-join-url"><?php esc_html_e( 'Kopieren', 'event-tracker' ); ?></button>
							<button type="button" class="et-zm-btn" data-zm-action="adopt-redirect" title="<?php esc_attr_e( 'Join-URL als Redirect-URL uebernehmen', 'event-tracker' ); ?>"><?php esc_html_e( 'In Redirect-URL', 'event-tracker' ); ?></button>
						</div>
						<button type="button" class="et-zm-btn" data-zm-action="refresh-links"><?php esc_html_e( 'Links aktualisieren', 'event-tracker' ); ?></button>
					</div>

					<!-- Co-Hosts Section -->
					<div class="et-zm-section">
						<h4><?php esc_html_e( 'Co-Hosts', 'event-tracker' ); ?></h4>
						<div class="et-zm-user-search">
							<input type="text" id="et-zm-user-search" class="et-zm-search-input" placeholder="<?php esc_attr_e( 'WP-Benutzer suchen...', 'event-tracker' ); ?>" autocomplete="off" />
							<div class="et-zm-user-results" id="et-zm-user-results"></div>
						</div>
						<div class="et-zm-cohost-tags" id="et-zm-cohost-tags">
							<?php foreach ( $cohosts_arr as $email ) : ?>
								<span class="et-zm-tag" data-email="<?php echo esc_attr( $email ); ?>">
									<?php echo esc_html( $email ); ?>
									<span class="et-zm-tag-remove">&times;</span>
								</span>
							<?php endforeach; ?>
						</div>
						<button type="button" class="et-zm-btn primary" data-zm-action="save-cohosts"><?php esc_html_e( 'Co-Hosts speichern', 'event-tracker' ); ?></button>
					</div>

					<!-- Recording Section -->
					<div class="et-zm-section">
						<h4><?php esc_html_e( 'Aufzeichnung', 'event-tracker' ); ?></h4>
						<div class="et-zm-link-row">
							<input type="text" id="et-zm-recording-url" value="<?php echo esc_attr( $zm_rec_url ); ?>" readonly />
							<?php if ( $zm_rec_url ) : ?>
								<button type="button" class="et-zm-btn" data-zm-action="copy" data-zm-target="et-zm-recording-url"><?php esc_html_e( 'Kopieren', 'event-tracker' ); ?></button>
								<button type="button" class="et-zm-btn" data-zm-action="adopt-recording"><?php esc_html_e( 'In Recording-URL', 'event-tracker' ); ?></button>
							<?php endif; ?>
						</div>
						<button type="button" class="et-zm-btn" data-zm-action="fetch-recording"><?php esc_html_e( 'Recording abrufen', 'event-tracker' ); ?></button>
					</div>

					<!-- Actions Section -->
					<div class="et-zm-section">
						<h4><?php esc_html_e( 'Aktionen', 'event-tracker' ); ?></h4>
						<div class="et-zm-actions">
							<button type="button" class="et-zm-btn" data-zm-action="test-connection"><?php esc_html_e( 'Verbindung testen', 'event-tracker' ); ?></button>
							<button type="button" class="et-zm-btn primary" data-zm-action="start-webinar"><?php esc_html_e( 'Webinar starten', 'event-tracker' ); ?></button>
							<button type="button" class="et-zm-btn danger" data-zm-action="delete-webinar"><?php esc_html_e( 'Webinar loeschen', 'event-tracker' ); ?></button>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/* =========================================================================
	 * Zoho Meeting AJAX Handlers
	 * ======================================================================= */

	/**
	 * Get Zoho Meeting Client (lazy-loaded)
	 *
	 * @return ZohoMeetingClient
	 */
	private function get_zm_client() {
		static $client = null;
		if ( null === $client ) {
			$client = new ZohoMeetingClient();
		}
		return $client;
	}

	/**
	 * Validate event for Zoho Meeting operations
	 *
	 * @return int Event ID or 0 on failure (sends JSON error).
	 */
	private function zm_validate_event() {
		check_ajax_referer( 'et_panels', 'nonce' );

		if ( ! Helpers::user_has_access() ) {
			wp_send_json_error( [ 'message' => __( 'Keine Berechtigung.', 'event-tracker' ) ] );
		}

		$event_id = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;

		if ( ! $event_id || get_post_type( $event_id ) !== Constants::CPT ) {
			wp_send_json_error( [ 'message' => __( 'Ungueltige Veranstaltung.', 'event-tracker' ) ] );
		}

		return $event_id;
	}

	/**
	 * Create Webinar (AJAX: et_zm_create_webinar)
	 */
	public function zm_create_webinar() {
		$event_id = $this->zm_validate_event();

		$title    = html_entity_decode( get_the_title( $event_id ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$start_ts = (int) get_post_meta( $event_id, Constants::META_START_TS, true );
		$end_ts   = (int) get_post_meta( $event_id, Constants::META_END_TS, true );

		Helpers::log( sprintf( 'zm_create_webinar: Event %d, Titel=%s, Start=%d, End=%d', $event_id, $title, $start_ts, $end_ts ), 'info' );

		if ( ! $start_ts || ! $end_ts ) {
			Helpers::log( 'zm_create_webinar: Abgebrochen — kein Start/End-Datum', 'error' );
			wp_send_json_error( [ 'message' => __( 'Event hat kein Start-/Enddatum.', 'event-tracker' ) ] );
		}

		// Zoho Meeting API erwartet Duration in Millisekunden
		$duration_ms = max( 60000, (int) ( ( $end_ts - $start_ts ) * 1000 ) );

		$tz = wp_timezone();
		$start_dt = new \DateTimeImmutable( '@' . $start_ts );
		$start_dt = $start_dt->setTimezone( $tz );

		$client = $this->get_zm_client();

		$payload = [
			'topic'     => $title,
			'startTime' => $start_dt->format( 'M d, Y h:i A' ),
			'duration'  => $duration_ms,
			'timezone'  => $tz->getName(),
		];

		Helpers::log( sprintf( 'zm_create_webinar: Sende an Zoho: %s', wp_json_encode( $payload ) ), 'info' );

		// Zoho Meeting Startzeit-Format: "Mar 18, 2026 04:00 PM"
		$result = $client->create_webinar( $payload );

		if ( ! $result['ok'] ) {
			$msg = $result['message'] ?? 'Unbekannter Fehler';
			Helpers::log( sprintf( 'zm_create_webinar: Zoho Fehler: %s', $msg ), 'error' );

			// Hilfreiche Fehlermeldungen fuer bekannte Zoho-Fehler
			if ( strpos( $msg, 'Presenter' ) !== false || strpos( $msg, 'ZUID' ) !== false ) {
				$msg .= ' — Der OAuth-Benutzer muss ein aktives Zoho Meeting Konto in der Organisation haben.';
			}

			wp_send_json_error( [ 'message' => $msg ] );
		}

		// Extract session key and URLs from response
		$data        = $result['data'];
		$session     = isset( $data['session'] ) ? $data['session'] : $data;
		$session_key = isset( $session['session_key'] ) ? $session['session_key'] : '';
		$start_url   = isset( $session['start_url'] ) ? $session['start_url'] : '';
		$join_url    = isset( $session['join_url'] ) ? $session['join_url'] : '';

		Helpers::log( sprintf( 'zm_create_webinar: Response parsed — key=%s, start_url=%s, join_url=%s', $session_key ?: '(leer)', $start_url ? 'ja' : '(leer)', $join_url ? 'ja' : '(leer)' ), 'info' );

		if ( $session_key ) {
			Helpers::begin_cap_override();
			update_post_meta( $event_id, Constants::META_ZM_KEY, $session_key );
			update_post_meta( $event_id, Constants::META_ZM_START_URL, $start_url );
			update_post_meta( $event_id, Constants::META_ZM_JOIN_URL, $join_url );
			update_post_meta( $event_id, Constants::META_ZM_STATUS, 'created' );
			Helpers::end_cap_override();
		}

		Helpers::log( sprintf( 'Zoho Meeting erstellt fuer Event %d: %s', $event_id, $session_key ), 'info' );

		wp_send_json_success( [
			'message'     => __( 'Webinar erfolgreich erstellt.', 'event-tracker' ),
			'session_key' => $session_key,
			'start_url'   => $start_url,
			'join_url'    => $join_url,
			'status'      => 'created',
		] );
	}

	/**
	 * Get Links (AJAX: et_zm_get_links)
	 */
	public function zm_get_links() {
		$event_id = $this->zm_validate_event();

		$session_key = get_post_meta( $event_id, Constants::META_ZM_KEY, true );

		if ( ! $session_key ) {
			wp_send_json_error( [ 'message' => __( 'Kein Webinar verknuepft.', 'event-tracker' ) ] );
		}

		$client = $this->get_zm_client();
		$result = $client->get_webinar( $session_key );

		if ( ! $result['ok'] ) {
			wp_send_json_error( [ 'message' => $result['message'] ] );
		}

		$data      = $result['data'];
		$session   = isset( $data['session'] ) ? $data['session'] : $data;
		$start_url = isset( $session['start_url'] ) ? $session['start_url'] : '';
		$join_url  = isset( $session['join_url'] ) ? $session['join_url'] : '';
		$status    = isset( $session['status'] ) ? strtolower( $session['status'] ) : 'created';

		Helpers::begin_cap_override();
		update_post_meta( $event_id, Constants::META_ZM_START_URL, $start_url );
		update_post_meta( $event_id, Constants::META_ZM_JOIN_URL, $join_url );
		update_post_meta( $event_id, Constants::META_ZM_STATUS, $status );
		Helpers::end_cap_override();

		wp_send_json_success( [
			'start_url' => $start_url,
			'join_url'  => $join_url,
			'status'    => $status,
		] );
	}

	/**
	 * Add Co-Hosts (AJAX: et_zm_add_cohosts)
	 */
	public function zm_add_cohosts() {
		$event_id = $this->zm_validate_event();

		$session_key = get_post_meta( $event_id, Constants::META_ZM_KEY, true );

		if ( ! $session_key ) {
			wp_send_json_error( [ 'message' => __( 'Kein Webinar verknuepft.', 'event-tracker' ) ] );
		}

		$emails_raw = isset( $_POST['emails'] ) ? wp_unslash( $_POST['emails'] ) : '';
		$emails     = is_array( $emails_raw ) ? array_map( 'sanitize_email', $emails_raw ) : [];
		$emails     = array_filter( $emails );

		if ( empty( $emails ) ) {
			wp_send_json_error( [ 'message' => __( 'Keine E-Mail-Adressen angegeben.', 'event-tracker' ) ] );
		}

		$client  = $this->get_zm_client();
		$success = [];
		$errors  = [];

		foreach ( $emails as $email ) {
			$result = $client->add_cohost( $session_key, $email );
			if ( $result['ok'] ) {
				$success[] = $email;
			} else {
				$errors[] = $email . ': ' . $result['message'];
			}
		}

		// Merge new co-hosts with existing ones
		$existing_json = get_post_meta( $event_id, Constants::META_ZM_COHOSTS, true );
		$existing      = $existing_json ? json_decode( $existing_json, true ) : [];
		if ( ! is_array( $existing ) ) {
			$existing = [];
		}
		$merged = array_values( array_unique( array_merge( $existing, $success ) ) );

		Helpers::begin_cap_override();
		update_post_meta( $event_id, Constants::META_ZM_COHOSTS, wp_json_encode( $merged ) );
		Helpers::end_cap_override();

		$message = sprintf(
			__( '%d Co-Host(s) hinzugefuegt.', 'event-tracker' ),
			count( $success )
		);

		if ( ! empty( $errors ) ) {
			$message .= ' ' . sprintf( __( 'Fehler: %s', 'event-tracker' ), implode( ', ', $errors ) );
		}

		wp_send_json_success( [
			'message'  => $message,
			'cohosts'  => $success,
			'errors'   => $errors,
		] );
	}

	/**
	 * Get Recording (AJAX: et_zm_get_recording)
	 */
	public function zm_get_recording() {
		$event_id = $this->zm_validate_event();

		$session_key = get_post_meta( $event_id, Constants::META_ZM_KEY, true );

		if ( ! $session_key ) {
			wp_send_json_error( [ 'message' => __( 'Kein Webinar verknuepft.', 'event-tracker' ) ] );
		}

		$client = $this->get_zm_client();
		$result = $client->get_recordings( $session_key );

		if ( ! $result['ok'] ) {
			wp_send_json_error( [ 'message' => $result['message'] ] );
		}

		$recordings = isset( $result['data']['recordings'] ) ? $result['data']['recordings'] : [];

		$recording_url = '';
		if ( ! empty( $recordings ) ) {
			// Take first recording's play URL
			$first = $recordings[0];
			$recording_url = isset( $first['play_url'] ) ? $first['play_url'] : '';
			if ( ! $recording_url && isset( $first['download_url'] ) ) {
				$recording_url = $first['download_url'];
			}
		}

		if ( $recording_url ) {
			Helpers::begin_cap_override();
			update_post_meta( $event_id, Constants::META_ZM_RECORDING_URL, $recording_url );
			Helpers::end_cap_override();
		}

		wp_send_json_success( [
			'recording_url' => $recording_url,
			'recordings'    => $recordings,
			'message'       => $recording_url
				? __( 'Aufzeichnung gefunden.', 'event-tracker' )
				: __( 'Keine Aufzeichnung verfuegbar.', 'event-tracker' ),
		] );
	}

	/**
	 * Test Connection (AJAX: et_zm_test_connection)
	 */
	public function zm_test_connection() {
		check_ajax_referer( 'et_panels', 'nonce' );

		if ( ! Helpers::user_has_access() ) {
			wp_send_json_error( [ 'message' => __( 'Keine Berechtigung.', 'event-tracker' ) ] );
		}

		$client = $this->get_zm_client();
		$result = $client->test_connection();

		if ( $result['ok'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * Start Webinar (AJAX: et_zm_start_webinar)
	 *
	 * Returns the start URL so the frontend can open it in a new tab.
	 */
	public function zm_start_webinar() {
		$event_id = $this->zm_validate_event();

		$start_url = get_post_meta( $event_id, Constants::META_ZM_START_URL, true );

		if ( ! $start_url ) {
			// Try refreshing from API
			$session_key = get_post_meta( $event_id, Constants::META_ZM_KEY, true );
			if ( $session_key ) {
				$client = $this->get_zm_client();
				$result = $client->get_webinar( $session_key );
				if ( $result['ok'] ) {
					$session   = isset( $result['data']['session'] ) ? $result['data']['session'] : $result['data'];
					$start_url = isset( $session['start_url'] ) ? $session['start_url'] : '';
					if ( $start_url ) {
						Helpers::begin_cap_override();
						update_post_meta( $event_id, Constants::META_ZM_START_URL, $start_url );
						Helpers::end_cap_override();
					}
				}
			}
		}

		if ( ! $start_url ) {
			wp_send_json_error( [ 'message' => __( 'Kein Start-Link verfuegbar.', 'event-tracker' ) ] );
		}

		Helpers::begin_cap_override();
		update_post_meta( $event_id, Constants::META_ZM_STATUS, 'started' );
		Helpers::end_cap_override();

		wp_send_json_success( [
			'start_url' => $start_url,
			'message'   => __( 'Webinar wird gestartet...', 'event-tracker' ),
		] );
	}

	/**
	 * Delete Webinar (AJAX: et_zm_delete_webinar)
	 */
	public function zm_delete_webinar() {
		$event_id = $this->zm_validate_event();

		$session_key = get_post_meta( $event_id, Constants::META_ZM_KEY, true );

		if ( ! $session_key ) {
			wp_send_json_error( [ 'message' => __( 'Kein Webinar verknuepft.', 'event-tracker' ) ] );
		}

		$client = $this->get_zm_client();
		$result = $client->delete_webinar( $session_key );

		if ( ! $result['ok'] ) {
			wp_send_json_error( [ 'message' => $result['message'] ] );
		}

		// Clear all Zoho Meeting meta
		Helpers::begin_cap_override();
		delete_post_meta( $event_id, Constants::META_ZM_KEY );
		delete_post_meta( $event_id, Constants::META_ZM_START_URL );
		delete_post_meta( $event_id, Constants::META_ZM_JOIN_URL );
		delete_post_meta( $event_id, Constants::META_ZM_RECORDING_URL );
		delete_post_meta( $event_id, Constants::META_ZM_COHOSTS );
		delete_post_meta( $event_id, Constants::META_ZM_STATUS );
		Helpers::end_cap_override();

		Helpers::log( sprintf( 'Zoho Meeting geloescht fuer Event %d', $event_id ), 'info' );

		wp_send_json_success( [ 'message' => __( 'Webinar geloescht.', 'event-tracker' ) ] );
	}

	/**
	 * Search WP Users for Co-Host selection (AJAX: et_zm_search_users)
	 */
	public function zm_search_users() {
		check_ajax_referer( 'et_panels', 'nonce' );

		if ( ! Helpers::user_has_access() ) {
			wp_send_json_error( [ 'message' => __( 'Keine Berechtigung.', 'event-tracker' ) ] );
		}

		$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

		if ( strlen( $search ) < 2 ) {
			wp_send_json_success( [ 'users' => [] ] );
		}

		$users = get_users( [
			'search'         => '*' . $search . '*',
			'search_columns' => [ 'user_login', 'user_email', 'display_name' ],
			'number'         => 10,
			'fields'         => [ 'ID', 'display_name', 'user_email' ],
		] );

		$results = [];
		foreach ( $users as $user ) {
			$results[] = [
				'id'    => (int) $user->ID,
				'name'  => $user->display_name,
				'email' => $user->user_email,
			];
		}

		wp_send_json_success( [ 'users' => $results ] );
	}
}
