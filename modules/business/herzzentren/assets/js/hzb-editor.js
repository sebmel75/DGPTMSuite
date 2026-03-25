(function($){
	'use strict';

	// --- Modal helpers ---
	function openModal($wrap){
		$wrap.find('.hzb-editor-modal').show();
		$('body').addClass('hzb-modal-open');
	}
	function closeModal($wrap){
		$wrap.find('.hzb-editor-modal').hide();
		$('body').removeClass('hzb-modal-open');
	}

	// --- Content rendering (shared by modal + inline) ---
	function renderContent($wrap, html){
		$wrap.find('.hzb-editor-modal__content').html(html);
		initializeWysiwyg($wrap);
	}

	function initializeWysiwyg($wrap){
		if ( typeof window.wp === 'undefined' || !wp.editor ) return;
		$wrap.find('textarea[data-wysiwyg="1"]').each(function(){
			var id = $(this).attr('id');
			try {
				if ( typeof tinymce !== 'undefined' && tinymce.get(id) ) {
					wp.editor.remove(id);
				}
				wp.editor.initialize(id, {
					tinymce: true,
					quicktags: true,
					mediaButtons: true
				});
			} catch(e){}
		});
	}

	function loadForm($wrap, postId){
		$wrap.find('.hzb-editor-modal__content').html('<p>Laden …</p>');
		$.post(HZB_EDITOR.ajaxUrl, {
			action: 'hzb_load_herzzentrum_edit_form',
			nonce: HZB_EDITOR.nonce,
			post_id: postId || 0
		}).done(function(resp){
			if ( resp && resp.success && resp.data && resp.data.html ) {
				renderContent($wrap, resp.data.html);
			} else {
				var err = (resp && resp.data && resp.data.message) ? resp.data.message : HZB_EDITOR.i18n.error;
				$wrap.find('.hzb-editor-modal__content').html('<div class="hzb-error">'+err+'</div>');
			}
		}).fail(function(){
			$wrap.find('.hzb-editor-modal__content').html('<div class="hzb-error">'+HZB_EDITOR.i18n.error+'</div>');
		});
	}

	// ===========================================================
	// Event-Handler via $(document).on() — Dashboard-kompatibel
	// ===========================================================

	// --- Modal: Button öffnet Modal und lädt Formular ---
	$(document).on('click', '.hzb-edit-form-btn', function(e){
		e.preventDefault();
		var $wrap = $(this).closest('.hzb-editor-wrapper');
		openModal($wrap);
		loadForm($wrap, $(this).data('hzb-post-id') || 0);
	});

	$(document).on('click', '.hzb-editor-modal__close, .hzb-editor-modal__overlay', function(){
		var $wrap = $(this).closest('.hzb-editor-wrapper');
		closeModal($wrap);
	});

	// --- Herzzentrum-Auswahl (wenn mehrere bearbeitbar) ---
	$(document).on('click', '.hzb-choose-hz', function(e){
		e.preventDefault();
		var $wrap = $(this).closest('.hzb-editor-wrapper');
		var pid = $wrap.find('#hzb-select-herzzentrum').val();
		loadForm($wrap, pid);
	});

	// --- Formular speichern ---
	$(document).on('submit', '#hzb-editor-form', function(e){
		e.preventDefault();
		var $form = $(this);

		// WYSIWYG synchronisieren
		if ( typeof tinymce !== 'undefined' ) {
			$form.find('textarea[data-wysiwyg="1"]').each(function(){
				var id = $(this).attr('id');
				var ed = tinymce.get(id);
				if ( ed ) $(this).val(ed.getContent());
			});
		}

		$form.addClass('is-saving');

		$.post(HZB_EDITOR.ajaxUrl, $form.serialize())
		.done(function(resp){
			if ( resp && resp.success ) {
				$form.removeClass('is-saving').addClass('is-saved');
				setTimeout(function(){ $form.removeClass('is-saved'); }, 1200);
			} else {
				var err = (resp && resp.data && resp.data.message) ? resp.data.message : HZB_EDITOR.i18n.error;
				alert(err);
				$form.removeClass('is-saving');
			}
		})
		.fail(function(){
			alert(HZB_EDITOR.i18n.error);
			$form.removeClass('is-saving');
		});
	});

	// --- Bild-Auswahl ---
	$(document).on('click', '.hzb-pick-image', function(e){
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

	$(document).on('click', '.hzb-remove-image', function(e){
		e.preventDefault();
		var $ctrl = $(this).closest('.hzb-image-control');
		$('#' + $ctrl.data('target')).val('');
		$ctrl.find('.hzb-image-preview').html('<span class="hzb-noimg">Kein Bild</span>');
	});

	// --- Inline-Modus: Automatisch laden (Dashboard-Tabs) ---
	function initInlineForms(){
		$('.hzb-editor-inline').not('[data-hzb-loaded]').each(function(){
			var $wrap = $(this);
			$wrap.attr('data-hzb-loaded', '1');
			loadForm($wrap, $wrap.data('hzb-post-id') || 0);
		});
	}

	$(document).ready(initInlineForms);
	$(document).on('dgptm_tab_loaded', initInlineForms);

})(jQuery);
