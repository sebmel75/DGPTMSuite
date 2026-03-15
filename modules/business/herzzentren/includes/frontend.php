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

	// Use same wrapper structure as hzb_edit_form_link but without modal
	// hzb-editor.js renderContent() looks for .hzb-editor-modal__content
	ob_start();
	?>
	<div class="hzb-editor-wrapper hzb-editor-inline" data-hzb-version="<?php echo esc_attr(DGPTM_HZ_VERSION); ?>">
		<div class="hzb-editor-modal__content">
			<p><?php esc_html_e('Formular wird geladen...','dgptm-hzb'); ?></p>
		</div>
	</div>
	<script>
	jQuery(function($){
		var $wrap = $('.hzb-editor-inline');
		if (!$wrap.length || $wrap.data('hzb-loaded')) return;
		$wrap.data('hzb-loaded', true);

		var postId = <?php echo intval($post_id); ?>;

		$.post(HZB_EDITOR.ajaxUrl, {
			action: 'hzb_load_herzzentrum_edit_form',
			nonce: HZB_EDITOR.nonce,
			post_id: postId
		}).done(function(resp){
			if (resp && resp.success && resp.data && resp.data.html) {
				// Use the same renderContent from hzb-editor.js pattern
				var $content = $wrap.find('.hzb-editor-modal__content');
				$content.html(resp.data.html);

				// Initialize WYSIWYG (same as hzb-editor.js initializeWysiwyg)
				if (typeof wp !== 'undefined' && wp.editor) {
					$content.find('textarea[data-wysiwyg="1"]').each(function(){
						var id = $(this).attr('id');
						try {
							if (typeof tinymce !== 'undefined' && tinymce.get(id)) wp.editor.remove(id);
							wp.editor.initialize(id, { tinymce: true, quicktags: true, mediaButtons: true });
						} catch(e){}
					});
				}

				// Bind select step (same as hzb-editor.js bindSelectStep)
				$wrap.on('click', '.hzb-choose-hz', function(e){
					e.preventDefault();
					var pid = $wrap.find('#hzb-select-herzzentrum').val();
					$content.html('<p>Laden...</p>');
					$.post(HZB_EDITOR.ajaxUrl, {
						action: 'hzb_load_herzzentrum_edit_form',
						nonce: HZB_EDITOR.nonce,
						post_id: pid
					}).done(function(r){
						if (r && r.success && r.data && r.data.html) {
							$content.html(r.data.html);
							// Re-init WYSIWYG
							if (typeof wp !== 'undefined' && wp.editor) {
								$content.find('textarea[data-wysiwyg="1"]').each(function(){
									var id = $(this).attr('id');
									try {
										if (typeof tinymce !== 'undefined' && tinymce.get(id)) wp.editor.remove(id);
										wp.editor.initialize(id, { tinymce: true, quicktags: true, mediaButtons: true });
									} catch(e){}
								});
							}
							// Re-bind form + image pickers
							bindInlineForm($wrap);
						}
					});
				});

				bindInlineForm($wrap);
			} else {
				var err = (resp && resp.data && resp.data.message) ? resp.data.message : 'Fehler';
				$wrap.find('.hzb-editor-modal__content').html('<p style="color:red">' + err + '</p>');
			}
		}).fail(function(){
			$wrap.find('.hzb-editor-modal__content').html('<p style="color:red">Laden fehlgeschlagen</p>');
		});

		function bindInlineForm($w) {
			// Form submit (same as hzb-editor.js bindFormSubmit)
			$w.off('submit.hzb','#hzb-editor-form');
			$w.on('submit.hzb','#hzb-editor-form', function(e){
				e.preventDefault();
				var $form = $(this);
				if (typeof tinymce !== 'undefined') {
					$form.find('textarea[data-wysiwyg="1"]').each(function(){
						var id = $(this).attr('id');
						var ed = tinymce.get(id);
						if (ed) $(this).val(ed.getContent());
					});
				}
				$form.addClass('is-saving');
				$.post(HZB_EDITOR.ajaxUrl, $form.serialize())
				.done(function(resp){
					if (resp && resp.success) {
						$form.removeClass('is-saving').addClass('is-saved');
						setTimeout(function(){ $form.removeClass('is-saved'); }, 1200);
					} else {
						var err = (resp && resp.data && resp.data.message) ? resp.data.message : HZB_EDITOR.i18n.error;
						alert(err);
						$form.removeClass('is-saving');
					}
				}).fail(function(){
					alert(HZB_EDITOR.i18n.error);
					$form.removeClass('is-saving');
				});
			});

			// Image pickers (same as hzb-editor.js bindImagePickers)
			$w.off('click.hzb-img','.hzb-pick-image');
			$w.on('click.hzb-img','.hzb-pick-image', function(e){
				e.preventDefault();
				var $ctrl = $(this).closest('.hzb-image-control');
				var target = $ctrl.data('target');
				var frame = wp.media({ title: HZB_EDITOR.i18n.upload, multiple: false });
				frame.on('select', function(){
					var att = frame.state().get('selection').first().toJSON();
					$('#' + target).val(att.id);
					$ctrl.find('.hzb-image-preview').html('<img src="'+(att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url)+'" alt="">');
				});
				frame.open();
			});
			$w.off('click.hzb-imgrm','.hzb-remove-image');
			$w.on('click.hzb-imgrm','.hzb-remove-image', function(e){
				e.preventDefault();
				var $ctrl = $(this).closest('.hzb-image-control');
				$('#' + $ctrl.data('target')).val('');
				$ctrl.find('.hzb-image-preview').html('<span class="hzb-noimg">Kein Bild</span>');
			});
		}
	});
	</script>
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
