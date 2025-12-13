<?php
/**
 * Event Tracker CPT Registration
 *
 * @package EventTracker\Admin
 * @since 2.0.0
 */

namespace EventTracker\Admin;

use EventTracker\Core\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CPT Class
 *
 * Registriert alle Custom Post Types und deren Metaboxen
 */
class CPT {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'register_cpt' ] );
		add_action( 'init', [ $this, 'register_mail_cpts' ] );
		add_action( 'init', [ $this, 'add_rewrite' ] );
		add_filter( 'query_vars', [ $this, 'register_query_vars' ] );

		// Metaboxen
		add_action( 'add_meta_boxes', [ $this, 'add_metabox' ] );
		add_action( 'save_post_' . Constants::CPT, [ $this, 'save_metabox' ] );

		// Admin-Spalten
		add_filter( 'manage_edit-' . Constants::CPT . '_columns', [ $this, 'admin_columns' ] );
		add_action( 'manage_' . Constants::CPT . '_posts_custom_column', [ $this, 'admin_column_content' ], 10, 2 );
	}

	/**
	 * Register main CPT
	 */
	public function register_cpt() {
		$labels = [
			'name'               => __( 'Veranstaltungen', 'event-tracker' ),
			'singular_name'      => __( 'Veranstaltung', 'event-tracker' ),
			'menu_name'          => __( 'Event Tracker', 'event-tracker' ),
			'add_new_item'       => __( 'Veranstaltung hinzufügen', 'event-tracker' ),
			'edit_item'          => __( 'Veranstaltung bearbeiten', 'event-tracker' ),
			'search_items'       => __( 'Veranstaltungen durchsuchen', 'event-tracker' ),
			'not_found'          => __( 'Keine Veranstaltungen gefunden', 'event-tracker' ),
		];

		register_post_type(
			Constants::CPT,
			[
				'labels'          => $labels,
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_position'   => 25,
				'menu_icon'       => 'dashicons-calendar-alt',
				'supports'        => [ 'title' ],
				'capability_type' => 'et_event',
				'map_meta_cap'    => true,
			]
		);
	}

	/**
	 * Register mail CPTs
	 */
	public function register_mail_cpts() {
		register_post_type(
			Constants::CPT_MAIL_LOG,
			[
				'labels'          => [
					'name'          => __( 'Mail-Logs', 'event-tracker' ),
					'singular_name' => __( 'Mail-Log', 'event-tracker' ),
				],
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'edit.php?post_type=' . Constants::CPT,
				'supports'        => [ 'title', 'editor', 'author' ],
				'capability_type' => 'et_event',
				'map_meta_cap'    => true,
			]
		);

		register_post_type(
			Constants::CPT_MAIL_TPL,
			[
				'labels'          => [
					'name'          => __( 'Mail-Vorlagen', 'event-tracker' ),
					'singular_name' => __( 'Mail-Vorlage', 'event-tracker' ),
				],
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'edit.php?post_type=' . Constants::CPT,
				'supports'        => [ 'title', 'editor' ],
				'capability_type' => 'et_event',
				'map_meta_cap'    => true,
			]
		);
	}

	/**
	 * Add rewrite rules
	 */
	public function add_rewrite() {
		add_rewrite_rule( '^eventtracker/?$', 'index.php?' . Constants::QUERY_KEY_TRACKER . '=1', 'top' );
	}

	/**
	 * Register query vars
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function register_query_vars( $vars ) {
		$vars[] = Constants::QUERY_KEY_TRACKER;
		$vars[] = Constants::QUERY_KEY_EVENT;
		return $vars;
	}

	/**
	 * Add Metabox for Event Details
	 */
	public function add_metabox() {
		add_meta_box(
			'et_event_meta',
			__( 'Gültigkeitszeitraum & Weiterleitungslink', 'event-tracker' ),
			[ $this, 'render_metabox' ],
			Constants::CPT,
			'normal',
			'high'
		);
	}

	/**
	 * Render Metabox
	 *
	 * @param \WP_Post $post Post object.
	 */
	public function render_metabox( $post ) {
		wp_nonce_field( 'et_save_meta', 'et_meta_nonce' );
		$tz        = wp_timezone();
		$start_ts  = (int) get_post_meta( $post->ID, Constants::META_START_TS, true );
		$end_ts    = (int) get_post_meta( $post->ID, Constants::META_END_TS, true );
		$url       = (string) get_post_meta( $post->ID, Constants::META_REDIRECT_URL, true );
		$zoho_id   = (string) get_post_meta( $post->ID, Constants::META_ZOHO_ID, true );
		$rec_url   = (string) get_post_meta( $post->ID, Constants::META_RECORDING_URL, true );

		// Iframe-Optionen
		$iframe_on  = (string) get_post_meta( $post->ID, Constants::META_IFRAME_ENABLE, true ) === '1';
		$iframe_url = (string) get_post_meta( $post->ID, Constants::META_IFRAME_URL, true );

		// Mehrtägige Events
		$additional = get_post_meta( $post->ID, Constants::META_ADDITIONAL_DATES, true );
		if ( ! is_array( $additional ) ) {
			$additional = [];
		}

		$start_val = $start_ts ? ( new \DateTimeImmutable( '@' . $start_ts ) )->setTimezone( $tz )->format( 'Y-m-d\TH:i' ) : '';
		$end_val   = $end_ts   ? ( new \DateTimeImmutable( '@' . $end_ts ) )->setTimezone( $tz )->format( 'Y-m-d\TH:i' ) : '';
		?>
		<style>
			.et-meta-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
			.et-meta-grid .wide{grid-column:1/-1}
			.et-meta-grid input[type="datetime-local"],
			.et-meta-grid input[type="url"],
			.et-meta-grid input[type="text"]{width:100%}
			.et-help{font-size:12px;color:#666;margin-top:4px}
			.et-inline{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap}
			.et-additional-date{display:flex;gap:8px;align-items:center;margin-bottom:8px}
			.et-additional-date input{flex:1}
			.et-remove-date{padding:4px 8px;cursor:pointer;background:#dc3232;color:#fff;border:none;border-radius:3px}
			.et-remove-date:hover{background:#b32d2e}
			.et-add-date{margin-top:8px;padding:6px 12px;background:#2271b1;color:#fff;border:none;border-radius:3px;cursor:pointer}
			.et-add-date:hover{background:#135e96}
		</style>
		<div class="et-meta-grid">
			<div>
				<label for="et_start"><strong><?php esc_html_e( 'Gültig ab (Datum & Uhrzeit)', 'event-tracker' ); ?></strong></label>
				<input id="et_start" name="et_start" type="datetime-local" value="<?php echo esc_attr( $start_val ); ?>" />
				<div class="et-help"><?php esc_html_e( 'Zeitangaben im Webseiten-Zeitzonen-Kontext', 'event-tracker' ); ?></div>
			</div>
			<div>
				<label for="et_end"><strong><?php esc_html_e( 'Gültig bis (Datum & Uhrzeit)', 'event-tracker' ); ?></strong></label>
				<input id="et_end" name="et_end" type="datetime-local" value="<?php echo esc_attr( $end_val ); ?>" />
			</div>
			<div class="wide">
				<label for="et_url"><strong><?php esc_html_e( 'Weiterleitungs-URL', 'event-tracker' ); ?></strong></label>
				<input id="et_url" name="et_url" type="url" placeholder="https://example.com/ziel" value="<?php echo esc_attr( $url ); ?>" />
				<div class="et-help"><?php esc_html_e( 'Wohin weitergeleitet wird, wenn die Veranstaltung gerade gültig ist (sofern nicht die Iframe-Option aktiv ist).', 'event-tracker' ); ?></div>
			</div>
			<div class="wide">
				<label for="et_zoho_id"><strong><?php esc_html_e( 'Zoho-ID', 'event-tracker' ); ?></strong></label>
				<input id="et_zoho_id" name="et_zoho_id" type="text" placeholder="z. B. 1234567890" value="<?php echo esc_attr( $zoho_id ); ?>" />
				<div class="et-help"><?php esc_html_e( 'Wird für Mail-Webhook und Tracking verwendet.', 'event-tracker' ); ?></div>
			</div>
			<div class="wide">
				<label for="et_recording_url"><strong><?php esc_html_e( 'Aufzeichnungs-URL (optional)', 'event-tracker' ); ?></strong></label>
				<input id="et_recording_url" name="et_recording_url" type="url" placeholder="https://example.com/aufzeichnung" value="<?php echo esc_attr( $rec_url ); ?>" />
				<div class="et-help"><?php esc_html_e( 'Nach Veranstaltungsende wird – falls gesetzt – nach 10 Sekunden zu dieser URL weitergeleitet. Kein Webhook-Aufruf.', 'event-tracker' ); ?></div>
			</div>

			<!-- Iframe-Optionen -->
			<div class="wide">
				<label><strong><?php esc_html_e( 'Live-Stream: Iframe statt Weiterleitung', 'event-tracker' ); ?></strong></label>
				<div class="et-inline">
					<label for="et_iframe_enable" class="et-inline">
						<input id="et_iframe_enable" name="et_iframe_enable" type="checkbox" value="1" <?php checked( $iframe_on ); ?> />
						<?php esc_html_e( 'Aktivieren (Desktop: Popup, Smartphone: neues Fenster)', 'event-tracker' ); ?>
					</label>
				</div>
				<div class="et-help"><?php esc_html_e( 'Wenn aktiv und eine Iframe-URL hinterlegt ist, wird nach Webhook-Aufruf der Stream angezeigt statt zur Weiterleitungs-URL zu springen.', 'event-tracker' ); ?></div>
			</div>
			<div class="wide">
				<label for="et_iframe_url"><strong><?php esc_html_e( 'Iframe-URL (optional)', 'event-tracker' ); ?></strong></label>
				<input id="et_iframe_url" name="et_iframe_url" type="url" placeholder="https://teams.microsoft.com/convene/..." value="<?php echo esc_attr( $iframe_url ); ?>" />
				<div class="et-help"><?php esc_html_e( 'Beispiel (Teams Townhall): https://teams.microsoft.com/convene/townhall?eventId=...&sessionId=...', 'event-tracker' ); ?></div>
			</div>

			<!-- Mehrtägige Events -->
			<div class="wide">
				<label><strong><?php esc_html_e( 'Mehrtägige Events (zusätzliche Termine)', 'event-tracker' ); ?></strong></label>
				<div class="et-help"><?php esc_html_e( 'Fügen Sie weitere Zeiträume hinzu, in denen dieses Event gültig ist. Gleicher Link funktioniert für alle Termine.', 'event-tracker' ); ?></div>
				<div id="et-additional-dates-container">
					<?php if ( ! empty( $additional ) ) : ?>
						<?php foreach ( $additional as $idx => $range ) : ?>
							<?php
							$range_start = isset( $range['start'] ) ? ( new \DateTimeImmutable( '@' . $range['start'] ) )->setTimezone( $tz )->format( 'Y-m-d\TH:i' ) : '';
							$range_end   = isset( $range['end'] ) ? ( new \DateTimeImmutable( '@' . $range['end'] ) )->setTimezone( $tz )->format( 'Y-m-d\TH:i' ) : '';
							?>
							<div class="et-additional-date">
								<input type="datetime-local" name="et_additional_start[]" value="<?php echo esc_attr( $range_start ); ?>" placeholder="Start" />
								<input type="datetime-local" name="et_additional_end[]" value="<?php echo esc_attr( $range_end ); ?>" placeholder="Ende" />
								<button type="button" class="et-remove-date">×</button>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
				<button type="button" class="et-add-date"><?php esc_html_e( '+ Weiteren Termin hinzufügen', 'event-tracker' ); ?></button>
			</div>
		</div>
		<script>
		jQuery(document).ready(function($) {
			$('.et-add-date').on('click', function() {
				var html = '<div class="et-additional-date">' +
					'<input type="datetime-local" name="et_additional_start[]" placeholder="Start" />' +
					'<input type="datetime-local" name="et_additional_end[]" placeholder="Ende" />' +
					'<button type="button" class="et-remove-date">×</button>' +
					'</div>';
				$('#et-additional-dates-container').append(html);
			});
			$(document).on('click', '.et-remove-date', function() {
				$(this).closest('.et-additional-date').remove();
			});
		});
		</script>
		<?php
	}

	/**
	 * Save Metabox
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_metabox( $post_id ) {
		if ( ! isset( $_POST['et_meta_nonce'] ) || ! wp_verify_nonce( $_POST['et_meta_nonce'], 'et_save_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$tz = wp_timezone();

		$start = isset( $_POST['et_start'] ) ? sanitize_text_field( wp_unslash( $_POST['et_start'] ) ) : '';
		$end   = isset( $_POST['et_end'] ) ? sanitize_text_field( wp_unslash( $_POST['et_end'] ) ) : '';
		$url_r = isset( $_POST['et_url'] ) ? trim( wp_unslash( $_POST['et_url'] ) ) : '';
		$zoho  = isset( $_POST['et_zoho_id'] ) ? sanitize_text_field( wp_unslash( $_POST['et_zoho_id'] ) ) : '';
		$rec_r = isset( $_POST['et_recording_url'] ) ? trim( wp_unslash( $_POST['et_recording_url'] ) ) : '';

		// Iframe
		$iframe_enable = isset( $_POST['et_iframe_enable'] ) ? '1' : '0';
		$iframe_r      = isset( $_POST['et_iframe_url'] ) ? trim( wp_unslash( $_POST['et_iframe_url'] ) ) : '';

		$start_ts = 0;
		if ( $start ) {
			try {
				$start_ts = ( new \DateTimeImmutable( $start, $tz ) )->getTimestamp();
			} catch ( \Exception $e ) {
				// Invalid date
			}
		}
		$end_ts = 0;
		if ( $end ) {
			try {
				$end_ts = ( new \DateTimeImmutable( $end, $tz ) )->getTimestamp();
			} catch ( \Exception $e ) {
				// Invalid date
			}
		}

		$url    = $url_r ? esc_url_raw( $url_r, [ 'http', 'https' ] ) : '';
		$rec    = $rec_r ? esc_url_raw( $rec_r, [ 'http', 'https' ] ) : '';
		$iframe = $iframe_r ? esc_url_raw( $iframe_r, [ 'http', 'https' ] ) : '';

		update_post_meta( $post_id, Constants::META_START_TS, $start_ts );
		update_post_meta( $post_id, Constants::META_END_TS, $end_ts );
		update_post_meta( $post_id, Constants::META_REDIRECT_URL, $url );
		update_post_meta( $post_id, Constants::META_ZOHO_ID, $zoho );
		update_post_meta( $post_id, Constants::META_RECORDING_URL, $rec );
		update_post_meta( $post_id, Constants::META_IFRAME_ENABLE, $iframe_enable );
		update_post_meta( $post_id, Constants::META_IFRAME_URL, $iframe );

		// Mehrtägige Events
		$additional_dates = [];
		if ( isset( $_POST['et_additional_start'] ) && is_array( $_POST['et_additional_start'] ) ) {
			$starts = array_map( 'sanitize_text_field', array_map( 'wp_unslash', $_POST['et_additional_start'] ) );
			$ends   = isset( $_POST['et_additional_end'] ) ? array_map( 'sanitize_text_field', array_map( 'wp_unslash', $_POST['et_additional_end'] ) ) : [];

			foreach ( $starts as $idx => $start_input ) {
				$end_input = isset( $ends[ $idx ] ) ? $ends[ $idx ] : '';
				if ( ! $start_input || ! $end_input ) {
					continue;
				}

				$add_start = 0;
				$add_end   = 0;

				try {
					$add_start = ( new \DateTimeImmutable( $start_input, $tz ) )->getTimestamp();
				} catch ( \Exception $e ) {
					continue;
				}

				try {
					$add_end = ( new \DateTimeImmutable( $end_input, $tz ) )->getTimestamp();
				} catch ( \Exception $e ) {
					continue;
				}

				if ( $add_start && $add_end && $add_end > $add_start ) {
					$additional_dates[] = [
						'start' => $add_start,
						'end'   => $add_end,
					];
				}
			}
		}
		update_post_meta( $post_id, Constants::META_ADDITIONAL_DATES, $additional_dates );
	}

	/**
	 * Admin Columns
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function admin_columns( $columns ) {
		$new = [];
		foreach ( $columns as $key => $val ) {
			$new[ $key ] = $val;
			if ( $key === 'title' ) {
				$new['et_status']   = __( 'Status', 'event-tracker' );
				$new['et_timespan'] = __( 'Zeitraum', 'event-tracker' );
				$new['et_link']     = __( 'Link', 'event-tracker' );
			}
		}
		return $new;
	}

	/**
	 * Admin Column Content
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 */
	public function admin_column_content( $column, $post_id ) {
		$tz        = wp_timezone();
		$now       = time();
		$start_ts  = (int) get_post_meta( $post_id, Constants::META_START_TS, true );
		$end_ts    = (int) get_post_meta( $post_id, Constants::META_END_TS, true );
		$url       = (string) get_post_meta( $post_id, Constants::META_REDIRECT_URL, true );
		$iframe    = (string) get_post_meta( $post_id, Constants::META_IFRAME_URL, true );
		$additional = get_post_meta( $post_id, Constants::META_ADDITIONAL_DATES, true );

		switch ( $column ) {
			case 'et_status':
				// Check main time range
				$valid = ( $start_ts && $end_ts && $now >= $start_ts && $now <= $end_ts );

				// Check additional dates
				if ( ! $valid && is_array( $additional ) ) {
					foreach ( $additional as $range ) {
						if ( isset( $range['start'], $range['end'] ) && $now >= $range['start'] && $now <= $range['end'] ) {
							$valid = true;
							break;
						}
					}
				}

				if ( $valid ) {
					echo '<span style="color:green;">● ' . esc_html__( 'Aktiv', 'event-tracker' ) . '</span>';
				} elseif ( $start_ts && $now < $start_ts ) {
					echo '<span style="color:blue;">● ' . esc_html__( 'Bevorstehend', 'event-tracker' ) . '</span>';
				} elseif ( $end_ts && $now > $end_ts ) {
					// Check if any additional date is in future
					$has_future = false;
					if ( is_array( $additional ) ) {
						foreach ( $additional as $range ) {
							if ( isset( $range['start'] ) && $now < $range['start'] ) {
								$has_future = true;
								break;
							}
						}
					}
					if ( $has_future ) {
						echo '<span style="color:blue;">● ' . esc_html__( 'Bevorstehend', 'event-tracker' ) . '</span>';
					} else {
						echo '<span style="color:gray;">● ' . esc_html__( 'Beendet', 'event-tracker' ) . '</span>';
					}
				} else {
					echo '<span style="color:gray;">—</span>';
				}
				break;

			case 'et_timespan':
				if ( $start_ts && $end_ts ) {
					$start_dt = ( new \DateTimeImmutable( '@' . $start_ts ) )->setTimezone( $tz );
					$end_dt   = ( new \DateTimeImmutable( '@' . $end_ts ) )->setTimezone( $tz );
					echo esc_html( $start_dt->format( 'd.m.Y H:i' ) ) . '<br>';
					echo '<small>' . esc_html( $end_dt->format( 'd.m.Y H:i' ) ) . '</small>';

					if ( is_array( $additional ) && ! empty( $additional ) ) {
						echo '<br><small style="color:#666;">+' . count( $additional ) . ' ' . esc_html__( 'weitere', 'event-tracker' ) . '</small>';
					}
				} else {
					echo '—';
				}
				break;

			case 'et_link':
				$link = home_url( '/eventtracker?id=' . $post_id );
				echo '<a href="' . esc_url( $link ) . '" target="_blank" title="' . esc_attr__( 'In neuem Tab öffnen', 'event-tracker' ) . '">';
				echo esc_html__( 'Anzeigen', 'event-tracker' );
				echo '</a>';
				if ( ! $url && ! $iframe ) {
					echo '<br><small style="color:red;">' . esc_html__( 'Keine URL', 'event-tracker' ) . '</small>';
				}
				break;
		}
	}
}
