(function($){
	'use strict';

	function openModal($wrap){
		$wrap.find('.hzb-editor-modal').show();
		$('body').addClass('hzb-modal-open');
	}
	function closeModal($wrap){
		$wrap.find('.hzb-editor-modal').hide();
		$('body').removeClass('hzb-modal-open');
	}

	function renderContent($wrap, html){
		$wrap.find('.hzb-editor-modal__content').html(html);
		initializeWysiwyg($wrap);
		bindSelectStep($wrap);
		bindFormSubmit($wrap);
		bindImagePickers($wrap);
	}

	function initializeWysiwyg($wrap){
		if ( typeof window.wp === 'undefined' || !wp.editor ) return;
		$wrap.find('textarea[data-wysiwyg="1"]').each(function(){
			var id = $(this).attr('id');
			try {
				// Falls bereits initialisiert, vorher entfernen
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

	function bindSelectStep($wrap){
		$wrap.off('click.hzb-choose','.hzb-choose-hz');
		$wrap.on('click.hzb-choose','.hzb-choose-hz', function(e){
			e.preventDefault();
			var pid = $wrap.find('#hzb-select-herzzentrum').val();
			loadForm($wrap, pid);
		});
	}

	function bindFormSubmit($wrap){
		$wrap.off('submit.hzb','#hzb-editor-form');
		$wrap.on('submit.hzb','#hzb-editor-form', function(e){
			e.preventDefault();
			var $form = $(this);

			// WYSIWYG Inhalte synchronisieren
			if ( typeof tinymce !== 'undefined' ) {
				$form.find('textarea[data-wysiwyg="1"]').each(function(){
					var id = $(this).attr('id');
					var ed = tinymce.get(id);
					if ( ed ) {
						$(this).val(ed.getContent());
					}
				});
			}

			var data = $form.serialize();
			$form.addClass('is-saving');

			$.post(HZB_EDITOR.ajaxUrl, data)
			 .done(function(resp){
				if ( resp && resp.success ) {
					var msg = (resp.data && resp.data.message) ? resp.data.message : HZB_EDITOR.i18n.saved;
					$form.removeClass('is-saving').addClass('is-saved');
					window.setTimeout(function(){ $form.removeClass('is-saved'); }, 1200);
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
	}

	function bindImagePickers($wrap){
		$wrap.off('click.hzb-img','.hzb-pick-image');
		$wrap.on('click.hzb-img','.hzb-pick-image', function(e){
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

		$wrap.off('click.hzb-imgrm','.hzb-remove-image');
		$wrap.on('click.hzb-imgrm','.hzb-remove-image', function(e){
			e.preventDefault();
			var $ctrl = $(this).closest('.hzb-image-control');
			var target = $ctrl.data('target');
			$('#' + target).val('');
			$ctrl.find('.hzb-image-preview').html('<span class="hzb-noimg">Kein Bild</span>');
		});
	}

	function loadForm($wrap, postId){
		var data = {
			action: 'hzb_load_herzzentrum_edit_form',
			nonce: HZB_EDITOR.nonce,
			post_id: postId || 0
		};
		$wrap.find('.hzb-editor-modal__content').html('<p>Laden …</p>');
		$.post(HZB_EDITOR.ajaxUrl, data)
		 .done(function(resp){
			if ( resp && resp.success && resp.data && resp.data.html ) {
				renderContent($wrap, resp.data.html);
			} else {
				var err = (resp && resp.data && resp.data.message) ? resp.data.message : HZB_EDITOR.i18n.error;
				$wrap.find('.hzb-editor-modal__content').html('<div class="hzb-error">'+err+'</div>');
			}
		 })
		 .fail(function(){
			$wrap.find('.hzb-editor-modal__content').html('<div class="hzb-error">'+HZB_EDITOR.i18n.error+'</div>');
		 });
	}

	$(document).on('click','.hzb-edit-form-btn', function(e){
		e.preventDefault();
		var $wrap = $(this).closest('.hzb-editor-wrapper');
		openModal($wrap);
		var pid = $(this).data('hzb-post-id') || 0;
		loadForm($wrap, pid);
	});

	$(document).on('click','.hzb-editor-modal__close, .hzb-editor-modal__overlay', function(e){
		var $wrap = $(this).closest('.hzb-editor-wrapper');
		closeModal($wrap);
	});

})(jQuery);
