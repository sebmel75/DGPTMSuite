<?php
/**
 * Event Tracker Shortcodes
 *
 * @package EventTracker\Frontend
 * @since 2.0.0
 */

namespace EventTracker\Frontend;

use EventTracker\Core\Constants;
use EventTracker\Core\Helpers;
use EventTracker\Frontend\MailerUI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcodes Class
 */
class Shortcodes {

	/**
	 * Script enqueued flag
	 *
	 * @var bool
	 */
	private static $script_added = false;

	/**
	 * Constructor
	 */
	public function __construct() {
		add_shortcode( 'event_tracker', [ $this, 'event_tracker_shortcode' ] );
		add_shortcode( 'event_mailer', [ $this, 'event_tracker_shortcode' ] ); // Alias
	}

	/**
	 * Event Tracker Shortcode
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function event_tracker_shortcode( $atts ) {
		// WICHTIG: jQuery sofort enqueuen
		wp_enqueue_script( 'jquery' );

		$output = '';

		// Handle frontend save if form submitted
		if ( isset( $_POST['et_save_event'] ) ) {
			$output .= $this->handle_frontend_save();
		}

		// Enqueue inline script once
		$this->enqueue_panels_script();

		ob_start();
		?>
		<div class="et-panels">
			<div class="et-panel-controls">
				<button type="button" class="et-btn" data-panel="list"><?php esc_html_e( 'Veranstaltungen anzeigen', 'event-tracker' ); ?></button>
				<?php if ( is_user_logged_in() ) : ?>
					<button type="button" class="et-btn" data-panel="form" data-form-mode="new"><?php esc_html_e( 'Veranstaltung hinzufügen', 'event-tracker' ); ?></button>
				<?php endif; ?>
			</div>
			<div class="et-panel et-hidden" data-name="list" aria-hidden="true"></div>
			<div class="et-panel et-hidden" data-name="form" aria-hidden="true"></div>
			<div class="et-msg et-hidden" role="alert" aria-live="polite"></div>
		</div>
		<?php
		$output .= ob_get_clean();

		// Add mailer section if user has access
		if ( Helpers::user_has_access() ) {
			$output .= MailerUI::render();
		}

		return $output;
	}

	/**
	 * Handle Frontend Event Save
	 *
	 * @return string Notice HTML
	 */
	private function handle_frontend_save() {
		if ( ! is_user_logged_in() ) {
			return Helpers::notice( __( 'Sie müssen eingeloggt sein, um eine Veranstaltung zu speichern.', 'event-tracker' ), 'error' );
		}

		if ( ! isset( $_POST['et_front_nonce'] ) || ! wp_verify_nonce( $_POST['et_front_nonce'], 'et_front_save' ) ) {
			return Helpers::notice( __( 'Sicherheitsprüfung fehlgeschlagen.', 'event-tracker' ), 'error' );
		}

		$event_id = isset( $_POST['et_event_id'] ) ? absint( $_POST['et_event_id'] ) : 0;
		$title    = isset( $_POST['et_title'] ) ? sanitize_text_field( wp_unslash( $_POST['et_title'] ) ) : '';
		$start    = isset( $_POST['et_start'] ) ? sanitize_text_field( wp_unslash( $_POST['et_start'] ) ) : '';
		$end      = isset( $_POST['et_end'] ) ? sanitize_text_field( wp_unslash( $_POST['et_end'] ) ) : '';
		$url_raw  = isset( $_POST['et_url'] ) ? trim( wp_unslash( $_POST['et_url'] ) ) : '';
		$zoho     = isset( $_POST['et_zoho_id'] ) ? sanitize_text_field( wp_unslash( $_POST['et_zoho_id'] ) ) : '';
		$rec_raw  = isset( $_POST['et_recording_url'] ) ? trim( wp_unslash( $_POST['et_recording_url'] ) ) : '';
		$if_enable = isset( $_POST['et_iframe_enable'] ) ? '1' : '0';
		$if_raw    = isset( $_POST['et_iframe_url'] ) ? trim( wp_unslash( $_POST['et_iframe_url'] ) ) : '';

		// Validate required fields
		if ( ! $title || ! $start || ! $end || ! $url_raw ) {
			return Helpers::notice( __( 'Bitte fülle alle Pflichtfelder aus.', 'event-tracker' ), 'error' );
		}

		// Parse dates
		$start_ts = Helpers::sanitize_datetime_local( $start );
		$end_ts   = Helpers::sanitize_datetime_local( $end );

		if ( ! $start_ts || ! $end_ts || $end_ts <= $start_ts ) {
			return Helpers::notice( __( 'Ungültiger Zeitraum (Ende muss nach Start liegen).', 'event-tracker' ), 'error' );
		}

		// Sanitize URLs
		$url = esc_url_raw( $url_raw, [ 'http', 'https' ] );
		$rec = $rec_raw ? esc_url_raw( $rec_raw, [ 'http', 'https' ] ) : '';
		$iframe_url = $if_raw ? esc_url_raw( $if_raw, [ 'http', 'https' ] ) : '';

		// Enable cap override for saving
		Helpers::begin_cap_override();

		// Create or update event
		if ( $event_id && get_post_type( $event_id ) === Constants::CPT ) {
			// Update existing
			wp_update_post(
				[
					'ID'         => $event_id,
					'post_title' => $title,
				]
			);
			$message = __( 'Veranstaltung erfolgreich aktualisiert.', 'event-tracker' );
		} else {
			// Create new
			$event_id = wp_insert_post(
				[
					'post_type'   => Constants::CPT,
					'post_title'  => $title,
					'post_status' => 'publish',
					'post_author' => get_current_user_id(),
				]
			);
			$message = __( 'Veranstaltung erfolgreich erstellt.', 'event-tracker' );
		}

		if ( is_wp_error( $event_id ) || ! $event_id ) {
			Helpers::end_cap_override();
			return Helpers::notice( __( 'Fehler beim Speichern der Veranstaltung.', 'event-tracker' ), 'error' );
		}

		// Save meta fields
		update_post_meta( $event_id, Constants::META_START_TS, $start_ts );
		update_post_meta( $event_id, Constants::META_END_TS, $end_ts );
		update_post_meta( $event_id, Constants::META_REDIRECT_URL, $url );
		update_post_meta( $event_id, Constants::META_ZOHO_ID, $zoho );
		update_post_meta( $event_id, Constants::META_RECORDING_URL, $rec );
		update_post_meta( $event_id, Constants::META_IFRAME_ENABLE, $if_enable );
		update_post_meta( $event_id, Constants::META_IFRAME_URL, $iframe_url );

		// Save additional dates (multi-day events)
		$additional_dates = [];
		if ( isset( $_POST['et_additional_start'] ) && is_array( $_POST['et_additional_start'] ) ) {
			$starts = array_map( 'sanitize_text_field', array_map( 'wp_unslash', $_POST['et_additional_start'] ) );
			$ends   = isset( $_POST['et_additional_end'] ) ? array_map( 'sanitize_text_field', array_map( 'wp_unslash', $_POST['et_additional_end'] ) ) : [];

			foreach ( $starts as $idx => $start_input ) {
				$end_input = isset( $ends[ $idx ] ) ? $ends[ $idx ] : '';
				if ( ! $start_input || ! $end_input ) {
					continue;
				}

				$add_start = Helpers::sanitize_datetime_local( $start_input );
				$add_end   = Helpers::sanitize_datetime_local( $end_input );

				if ( $add_start && $add_end && $add_end > $add_start ) {
					$additional_dates[] = [
						'start' => $add_start,
						'end'   => $add_end,
					];
				}
			}
		}
		update_post_meta( $event_id, Constants::META_ADDITIONAL_DATES, $additional_dates );

		Helpers::end_cap_override();

		Helpers::log( sprintf( 'Event saved via frontend: %s (ID: %d)', $title, $event_id ), 'info' );

		return Helpers::notice( $message, 'success' );
	}

	/**
	 * Enqueue frontend assets
	 */
	private function enqueue_panels_script() {
		if ( self::$script_added ) {
			return;
		}
		self::$script_added = true;

		$plugin     = \EventTracker\Core\Plugin::instance();
		$plugin_url = $plugin->plugin_url();
		$version    = \EventTracker\Core\Plugin::VERSION;

		// Enqueue CSS
		wp_enqueue_style(
			'event-tracker-frontend',
			$plugin_url . 'assets/css/frontend.css',
			[],
			$version
		);

		// Enqueue JavaScript
		wp_enqueue_script(
			'event-tracker-frontend',
			$plugin_url . 'assets/js/frontend.js',
			[ 'jquery' ],
			$version,
			true
		);

		// Localize script data
		wp_localize_script(
			'event-tracker-frontend',
			'eventTrackerData',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'et_panels' ),
			]
		);
	}
}
