<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Shortcode: [hzb_edit_form_link label="Herzzentrum bearbeiten" post_id=""]
 * - Zeigt Button, öffnet Modal und lädt AJAX-Formular (inkl. Auswahl, falls mehrere Herzzentren bearbeitbar).
 */
add_shortcode( 'hzb_edit_form_link', function( $atts = array(), $content = '' ) {

	$atts = shortcode_atts( array(
		'label'   => __('Herzzentrum bearbeiten','dgptm-hzb'),
		'post_id' => 0,
	), $atts, 'hzb_edit_form_link' );

	if ( ! is_user_logged_in() ) return '';

	$user_id = get_current_user_id();
	$post_id = intval( $atts['post_id'] );

	if ( $post_id <= 0 && is_singular('herzzentrum') ) {
		$post_id = get_the_ID();
	}

	$can_show = false;
	if ( $post_id > 0 ) {
		$can_show = hzb_user_can_edit_herzzentrum( $user_id, $post_id );
	} else {
		$editable = hzb_get_user_editable_herzzentren( $user_id );
		$can_show = ! empty( $editable );
	}

	if ( ! $can_show ) return '';

	// Assets nur laden, wenn der Button gerendert wird
	hzb_enqueue_editor_assets();

	ob_start(); ?>
	<div class="hzb-editor-wrapper" data-hzb-version="<?php echo esc_attr(DGPTM_HZ_VERSION); ?>">
		<button class="button button-primary hzb-edit-form-btn" data-hzb-post-id="<?php echo esc_attr( $post_id ); ?>"><?php echo esc_html( $atts['label'] ); ?></button>
		<div class="hzb-editor-modal" style="display:none" role="dialog" aria-modal="true">
			<div class="hzb-editor-modal__overlay"></div>
			<div class="hzb-editor-modal__inner" role="document">
				<button class="hzb-editor-modal__close" aria-label="<?php esc_attr_e('Schließen','dgptm-hzb'); ?>">&times;</button>
				<div class="hzb-editor-modal__content">
					<!-- AJAX-Content -->
				</div>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
} );

/**
 * Shortcode: [hzb_edit_form_content]
 * Zeigt das Herzzentrum-Bearbeitungsformular INLINE (ohne Modal).
 * Nutzt exakt denselben AJAX-Endpunkt und dasselbe JS (hzb-editor.js) wie [hzb_edit_form_link].
 * Rendert die gleiche Wrapper-Struktur, aber direkt sichtbar statt im Modal.
 */
add_shortcode( 'hzb_edit_form_content', function( $atts = array() ) {

	if ( ! is_user_logged_in() ) return '';

	$atts = shortcode_atts( array( 'post_id' => 0 ), $atts, 'hzb_edit_form_content' );
	$user_id = get_current_user_id();
	$post_id = intval( $atts['post_id'] );

	$editable_ids = hzb_get_user_editable_herzzentren( $user_id );
	if ( empty( $editable_ids ) ) return '';

	hzb_enqueue_editor_assets();

	// Kein Inline-Script — Initialisierung erfolgt in hzb-editor.js (Dashboard-kompatibel)
	ob_start();
	?>
	<div class="hzb-editor-wrapper hzb-editor-inline" data-hzb-version="<?php echo esc_attr(DGPTM_HZ_VERSION); ?>" data-hzb-post-id="<?php echo intval($post_id); ?>">
		<div class="hzb-editor-modal__content">
			<p><?php esc_html_e('Formular wird geladen...','dgptm-hzb'); ?></p>
		</div>
	</div>
	<?php
	return ob_get_clean();
} );

/**
 * Pre-load editor assets on dashboard pages (lazy-loaded tabs need assets ready)
 */
add_action('wp_enqueue_scripts', function() {
	global $post;
	if ( is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'dgptm_dashboard') ) {
		if ( is_user_logged_in() ) {
			$editable = hzb_get_user_editable_herzzentren( get_current_user_id() );
			if ( !empty($editable) ) {
				hzb_enqueue_editor_assets();
			}
		}
	}
});

/**
 * Editor-Assets (JS/CSS) + wp.editor
 */
function hzb_enqueue_editor_assets() {
	if ( ! did_action('wp_enqueue_media') ) {
		wp_enqueue_media();
	}
	if ( function_exists('wp_enqueue_editor') ) {
		wp_enqueue_editor();
	}

	wp_enqueue_style( 'hzb-editor', DGPTM_HZ_URL . 'assets/css/hzb-editor.css', array(), DGPTM_HZ_VERSION );
	wp_enqueue_script( 'hzb-editor', DGPTM_HZ_URL . 'assets/js/hzb-editor.js', array('jquery','wp-util','editor','clipboard'), DGPTM_HZ_VERSION, true );

	wp_localize_script( 'hzb-editor', 'HZB_EDITOR', array(
		'ajaxUrl' => admin_url('admin-ajax.php'),
		'nonce'   => wp_create_nonce('hzb_editor'),
		'i18n'    => array(
			'title_select' => __('Bitte Herzzentrum wählen','dgptm-hzb'),
			'btn_continue' => __('Weiter','dgptm-hzb'),
			'saved'        => __('Gespeichert','dgptm-hzb'),
			'error'        => __('Fehler','dgptm-hzb'),
			'upload'       => __('Medien auswählen','dgptm-hzb'),
		),
	) );
}
