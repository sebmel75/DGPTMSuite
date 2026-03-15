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
 * Bei mehreren Herzzentren: erst Auswahlliste, dann Formular per AJAX nachladen.
 * Kompatibel mit Dashboard-Tab-Struktur (AJAX lazy loading).
 */
add_shortcode( 'hzb_edit_form_content', function( $atts = array() ) {

	if ( ! is_user_logged_in() ) return '';

	$atts = shortcode_atts( array( 'post_id' => 0 ), $atts, 'hzb_edit_form_content' );
	$user_id = get_current_user_id();
	$post_id = intval( $atts['post_id'] );

	$editable_ids = hzb_get_user_editable_herzzentren( $user_id );
	if ( empty( $editable_ids ) ) return '';

	hzb_enqueue_editor_assets();

	ob_start();
	?>
	<div class="hzb-inline-editor" data-hzb-version="<?php echo esc_attr(DGPTM_HZ_VERSION); ?>">
		<div class="hzb-inline-editor__content">
		<?php
		if ( $post_id > 0 && in_array( $post_id, $editable_ids, true ) ) {
			// Direkt ein bestimmtes Herzzentrum
			echo hzb_render_edit_form( $post_id, $user_id );
		} elseif ( count( $editable_ids ) === 1 ) {
			// Nur ein Herzzentrum zugewiesen
			echo hzb_render_edit_form( intval( $editable_ids[0] ), $user_id );
		} else {
			// Mehrere: Auswahlliste, dann AJAX-Nachladen
			?>
			<div class="hzb-select-step">
				<h3><?php esc_html_e('Herzzentrum auswaehlen','dgptm-hzb'); ?></h3>
				<div class="hzb-field">
					<label for="hzb-inline-select"><?php esc_html_e('Herzzentrum','dgptm-hzb'); ?></label>
					<select id="hzb-inline-select" class="hzb-select" style="width:100%;max-width:400px;padding:8px;">
						<option value="">-- Bitte waehlen --</option>
						<?php
						$posts = get_posts( array(
							'post_type'      => 'herzzentrum',
							'post__in'       => $editable_ids,
							'orderby'        => 'title',
							'order'          => 'ASC',
							'posts_per_page' => -1,
							'post_status'    => array('publish','draft','pending','private'),
						) );
						foreach ( $posts as $p ) {
							echo '<option value="' . esc_attr($p->ID) . '">' . esc_html($p->post_title ?: ('#'.$p->ID)) . '</option>';
						}
						?>
					</select>
				</div>
				<button class="button button-primary hzb-inline-load-btn" style="margin-top:10px;" disabled>
					<?php esc_html_e('Formular laden','dgptm-hzb'); ?>
				</button>
			</div>
			<div class="hzb-inline-form-target"></div>
			<script>
			jQuery(function($){
				var $wrap = $('.hzb-inline-editor');
				var $select = $('#hzb-inline-select');
				var $btn = $wrap.find('.hzb-inline-load-btn');
				var $target = $wrap.find('.hzb-inline-form-target');

				$select.on('change', function(){ $btn.prop('disabled', !$(this).val()); });

				$btn.on('click', function(){
					var pid = $select.val();
					if (!pid) return;
					$btn.prop('disabled', true).text('Laden...');
					$target.html('<p>Formular wird geladen...</p>');
					$wrap.find('.hzb-select-step').slideUp(200);

					$.post(HZB_EDITOR.ajaxUrl, {
						action: 'hzb_load_herzzentrum_edit_form',
						nonce: HZB_EDITOR.nonce,
						post_id: pid
					}).done(function(r){
						if (r.success) {
							$target.html(r.data.html);
							// Init WYSIWYG editors
							$target.find('.hzb-wysiwyg').each(function(){
								var id = $(this).attr('id');
								if (id && typeof wp !== 'undefined' && wp.editor) {
									wp.editor.initialize(id, {
										tinymce: { toolbar1: 'formatselect,bold,italic,bullist,numlist,link,unlink', toolbar2: '' },
										quicktags: true
									});
								}
							});
							// Init form submit
							$target.find('.hzb-edit-form').off('submit').on('submit', function(ev){
								ev.preventDefault();
								var $form = $(this);
								var $submitBtn = $form.find('[type="submit"]');
								$submitBtn.prop('disabled', true).text('Speichern...');
								// Sync WYSIWYG
								$form.find('.hzb-wysiwyg').each(function(){
									var id = $(this).attr('id');
									if (id && typeof wp !== 'undefined' && wp.editor) {
										wp.editor.save(id);
									}
								});
								$.post(HZB_EDITOR.ajaxUrl, $form.serialize() + '&nonce=' + HZB_EDITOR.nonce)
								.done(function(r){
									if (r.success) {
										$submitBtn.text('Gespeichert!').css('background','#059669');
										setTimeout(function(){ $submitBtn.prop('disabled',false).text('Speichern').css('background',''); }, 2000);
									} else {
										alert(r.data && r.data.message ? r.data.message : 'Fehler beim Speichern');
										$submitBtn.prop('disabled',false).text('Speichern');
									}
								}).fail(function(){
									alert('Netzwerkfehler');
									$submitBtn.prop('disabled',false).text('Speichern');
								});
							});
							$(document).trigger('dgptm_tab_loaded', ['herzzentrum']);
						} else {
							$target.html('<p style="color:red">' + (r.data && r.data.message ? r.data.message : 'Fehler') + '</p>');
						}
					}).fail(function(){
						$target.html('<p style="color:red">Laden fehlgeschlagen</p>');
					});
				});
			});
			</script>
			<?php
		}
		?>
		</div>
	</div>
	<?php
	return ob_get_clean();
} );

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
