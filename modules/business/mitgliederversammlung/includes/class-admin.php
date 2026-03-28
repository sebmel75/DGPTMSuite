<?php
/**
 * Admin-Seite fuer Zoom-Einstellungen und Logs.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class DGPTM_MV_Admin {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'admin_menu' ] );
	}

	public function admin_menu() {
		add_options_page(
			'Mitgliederversammlung',
			'MV-Einstellungen',
			'manage_options',
			'dgptm-mv',
			[ $this, 'render_page' ]
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$mv = DGPTM_Mitgliederversammlung::instance();
		$zoom_settings = $mv->zoom->get_settings();
		$tab = sanitize_text_field( $_GET['tab'] ?? 'zoom' );

		?>
		<div class="wrap">
			<h1>Mitgliederversammlung - Einstellungen</h1>

			<nav class="nav-tab-wrapper">
				<a href="?page=dgptm-mv&tab=zoom" class="nav-tab <?php echo $tab === 'zoom' ? 'nav-tab-active' : ''; ?>">Zoom</a>
				<a href="?page=dgptm-mv&tab=log" class="nav-tab <?php echo $tab === 'log' ? 'nav-tab-active' : ''; ?>">Log</a>
			</nav>

			<?php if ( $tab === 'zoom' ) : ?>
				<form method="post" action="options.php">
					<?php settings_fields( 'dgptm_mv_zoom' ); ?>
					<table class="form-table">
						<tr>
							<th>Zoom aktivieren</th>
							<td><label><input type="checkbox" name="<?php echo DGPTM_MV_Zoom::OPT_KEY; ?>[zoom_enable]" value="1" <?php checked( $zoom_settings['zoom_enable'], 1 ); ?>> Zoom-Integration aktivieren</label></td>
						</tr>
						<tr>
							<th>Meeting-Typ</th>
							<td>
								<select name="<?php echo DGPTM_MV_Zoom::OPT_KEY; ?>[zoom_kind]">
									<option value="meeting" <?php selected( $zoom_settings['zoom_kind'], 'meeting' ); ?>>Meeting</option>
									<option value="webinar" <?php selected( $zoom_settings['zoom_kind'], 'webinar' ); ?>>Webinar</option>
								</select>
							</td>
						</tr>
						<tr>
							<th>Meeting-ID</th>
							<td><input type="text" name="<?php echo DGPTM_MV_Zoom::OPT_KEY; ?>[zoom_meeting_id]" value="<?php echo esc_attr( $zoom_settings['zoom_meeting_id'] ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th>S2S Account ID</th>
							<td><input type="text" name="<?php echo DGPTM_MV_Zoom::OPT_KEY; ?>[zoom_s2s_account_id]" value="<?php echo esc_attr( $zoom_settings['zoom_s2s_account_id'] ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th>S2S Client ID</th>
							<td><input type="text" name="<?php echo DGPTM_MV_Zoom::OPT_KEY; ?>[zoom_s2s_client_id]" value="<?php echo esc_attr( $zoom_settings['zoom_s2s_client_id'] ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th>S2S Client Secret</th>
							<td><input type="password" name="<?php echo DGPTM_MV_Zoom::OPT_KEY; ?>[zoom_s2s_client_secret]" value="<?php echo esc_attr( $zoom_settings['zoom_s2s_client_secret'] ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th>Webhook Secret</th>
							<td><input type="text" name="<?php echo DGPTM_MV_Zoom::OPT_KEY; ?>[zoom_webhook_secret]" value="<?php echo esc_attr( $zoom_settings['zoom_webhook_secret'] ); ?>" class="regular-text">
							<p class="description">HMAC SHA256 Secret fuer Webhook-Signatur-Validierung</p></td>
						</tr>
						<tr>
							<th>Logging</th>
							<td><label><input type="checkbox" name="<?php echo DGPTM_MV_Zoom::OPT_KEY; ?>[zoom_log_enable]" value="1" <?php checked( $zoom_settings['zoom_log_enable'], 1 ); ?>> Zoom-API-Log aktivieren</label></td>
						</tr>
					</table>
					<?php submit_button(); ?>
				</form>

			<?php elseif ( $tab === 'log' ) : ?>
				<?php
				$log = get_option( DGPTM_MV_Zoom::LOG_KEY, [] );
				if ( ! is_array( $log ) ) $log = [];
				?>
				<h2>Zoom API Log (letzte <?php echo count( $log ); ?> Eintraege)</h2>
				<?php if ( empty( $log ) ) : ?>
					<p>Keine Log-Eintraege.</p>
				<?php else : ?>
					<table class="widefat striped">
						<thead>
							<tr><th>Zeit</th><th>Phase</th><th>Status</th><th>Nachricht</th></tr>
						</thead>
						<tbody>
							<?php foreach ( array_slice( $log, 0, 100 ) as $entry ) : ?>
							<tr>
								<td><?php echo esc_html( $entry['time'] ?? '' ); ?></td>
								<td><?php echo esc_html( $entry['phase'] ?? '' ); ?></td>
								<td><?php echo esc_html( $entry['status'] ?? '' ); ?></td>
								<td><code><?php echo esc_html( $entry['message'] ?? '' ); ?></code></td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}
}
