<?php
/**
 * Beamer-/Projektions-Ansicht.
 *
 * Zeigt Live-Abstimmungen, Ergebnisse und QR-Codes.
 * Optimiert fuer Vollbild-Darstellung in Konferenzraeumen.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class DGPTM_MV_Beamer {

	public function shortcode_beamer( $atts ) {
		if ( ! DGPTM_Mitgliederversammlung::is_manager() ) {
			return '<p>Keine Berechtigung.</p>';
		}

		wp_enqueue_style( 'dgptm-mv-frontend' );
		wp_enqueue_script( 'dgptm-mv-beamer' );

		// Chart.js lokal, QRCode via CDN (kein brauchbares UMD-Bundle lokal verfuegbar)
		wp_enqueue_script( 'chartjs', DGPTM_MV_URL . 'assets/lib/chart.umd.min.js', [], '4.4.0', true );
		wp_enqueue_script( 'qrcodejs', 'https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js', [], '1.4.4', true );

		$assembly = DGPTM_MV_Assembly::get_active_assembly();
		$no_poll_text = 'Bitte warten...';

		ob_start();
		?>
		<div id="dgptm-mv-beamer" class="dgptm-mv-beamer-container">
			<!-- Uhr -->
			<div id="dgptm-mv-beamer-clock">
				<span id="dgptm-mv-clock-time">00:00:00</span>
				<span id="dgptm-mv-clock-separator">|</span>
				<span id="dgptm-mv-question-timer">0s</span>
			</div>

			<!-- Versammlungsname -->
			<div id="dgptm-mv-beamer-title" style="display:none;"></div>

			<!-- Logo / Warte-Bildschirm -->
			<div id="dgptm-mv-beamer-idle">
				<?php if ( $assembly && $assembly->logo_url ) : ?>
					<img src="<?php echo esc_url( $assembly->logo_url ); ?>" alt="Logo" class="dgptm-mv-beamer-logo">
				<?php endif; ?>
				<div class="dgptm-mv-beamer-filler"><?php echo esc_html( $no_poll_text ); ?></div>
			</div>

			<!-- Aktive Abstimmung -->
			<div id="dgptm-mv-beamer-live" style="display:none;">
				<div id="dgptm-mv-beamer-cta">Bitte abstimmen</div>
				<h2 id="dgptm-mv-beamer-question"></h2>
				<div id="dgptm-mv-beamer-majority-info"></div>
				<div id="dgptm-mv-beamer-live-bar"></div>
				<canvas id="dgptm-mv-beamer-chart"></canvas>
			</div>

			<!-- Alle Ergebnisse -->
			<div id="dgptm-mv-beamer-results-all" style="display:none;">
				<h2>Ergebnisuebersicht</h2>
				<div id="dgptm-mv-beamer-results-grid"></div>
			</div>

			<!-- QR-Code -->
			<div id="dgptm-mv-beamer-qr" style="display:none;">
				<canvas id="dgptm-mv-qr-canvas" width="200" height="200"></canvas>
				<small>Jetzt abstimmen</small>
			</div>

			<!-- Statistik-Leiste -->
			<div id="dgptm-mv-beamer-stats">
				<span id="dgptm-mv-beamer-stat-present">0 Anwesend</span>
				<span id="dgptm-mv-beamer-stat-eligible">0 Stimmberechtigt</span>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
