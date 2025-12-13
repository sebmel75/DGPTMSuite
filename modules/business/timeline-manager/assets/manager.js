jQuery(function($){
  function getNonce($scope){
    if (typeof TPL47!=='undefined' && TPL47.nonce){ return TPL47.nonce; }
    var n = $scope.closest('.tpl47-manager').find('.tpl47-open').data('nonce'); 
    return n || '';
  }
  function ajaxUrl(){ return (typeof TPL47!=='undefined' && TPL47.ajax) ? TPL47.ajax : '/wp-admin/admin-ajax.php'; }

  $(document).on('click', '.tpl47-open', function(e){
    e.preventDefault();
    var $btn = $(this);
    var $m = $btn.closest('.tpl47-manager').find('.tpl47-modal');
    $('body').addClass('tpl47-noscroll'); $m.fadeIn(120);
    $.post(ajaxUrl(), {action:'tpl47_list', nonce:getNonce($btn)}, function(resp){
      var $ul = $m.find('.tpl47-items').empty();
      if(!(resp && resp.success)){ alert((resp && resp.data && resp.data.message) || 'Fehler beim Laden.'); return; }
      if(resp.data.items.length===0){ $ul.append('<li>Keine Einträge.</li>'); return; }
      resp.data.items.forEach(function(it){
        var status = it.status==='draft' ? '<span class="tpl47-status draft">Entwurf</span>' : '<span class="tpl47-status publish">Veröffentlicht</span>';
        var thumb = it.thumb ? '<img class="tpl47-li-thumb" src="'+it.thumb+'" alt="">' : '<span class="tpl47-li-thumb placeholder"></span>';
        var label = (it.date?it.date:'—') + ' — ' + $('<div>').text(it.title).html();
        $ul.append('<li data-id="'+it.id+'">'+thumb+'<div class="tpl47-li-main"><a href="#" class="tpl47-load">'+label+'</a>'+status+'</div><a href="#" class="tpl47-del" title="Löschen">×</a></li>');
      });
    });
  });

  $(document).on('click', '.tpl47-close, .tpl47-cancel', function(e){
    e.preventDefault();
    var $m = $(this).closest('.tpl47-modal'); $m.fadeOut(120, function(){ $('body').removeClass('tpl47-noscroll'); });
  });

  $(document).on('click', '.tpl47-load', function(e){
    e.preventDefault();
    var $m = $(this).closest('.tpl47-modal'), $f = $m.find('form.tpl47-editor');
    var id = $(this).closest('li').data('id');
    $.post(ajaxUrl(), {action:'tpl47_load', nonce:getNonce($m), id:id}, function(resp){
      if(!(resp && resp.success)){ alert((resp && resp.data && resp.data.message) || 'Fehler beim Laden.'); return; }
      $f.find('[name=post_id]').val(resp.data.id);
      $f.find('[name=title]').val(resp.data.title||'');
      $f.find('[name=date]').val(resp.data.date||'');
      $f.find('[name=yearn]').val(resp.data.yearn||'');
      $f.find('[name=status]').val(resp.data.status||'publish');
      $f.find('[name=short]').val(resp.data.short||'');
      $f.find('[name=content]').val(resp.data.content||'');
      // Button
      $f.find('[name=btn_label]').val(resp.data.btn_label||'');
      $f.find('[name=btn_url]').val(resp.data.btn_url||'');
      $f.find('[name=btn_target]').prop('checked', resp.data.btn_target ? true : false);
      // Bild
      if(resp.data.thumb){
        $f.find('.tpl47-preview').attr('src', resp.data.thumb).show();
        $f.find('.tpl47-remove-image').show().data('post_id', resp.data.id);
      } else {
        $f.find('.tpl47-preview').hide().attr('src','');
        $f.find('.tpl47-remove-image').hide().data('post_id','');
      }
      $f.find('[name=image]').val('');
    });
  });

  $(document).on('click', '.tpl47-remove-image', function(e){
    e.preventDefault();
    if(!confirm(TPL47.i18n.removeImageConfirm)) return;
    var $btn = $(this);
    var post_id = $btn.data('post_id');
    var $m = $btn.closest('.tpl47-modal');
    if(!post_id) return;
    $.post(ajaxUrl(), {action:'tpl47_remove_image', nonce:getNonce($m), post_id:post_id}, function(resp){
      if(resp && resp.success){
        $btn.hide().data('post_id','');
        $m.find('.tpl47-preview').hide().attr('src','');
      } else {
        alert((resp && resp.data && resp.data.message) || 'Fehler.');
      }
    });
  });

  $(document).on('click', '.tpl47-resize-existing', function(e){
    e.preventDefault();
    if(!confirm(TPL47.i18n.resizeConfirm)) return;
    var $m = $(this).closest('.tpl47-modal');
    var $f = $m.find('form.tpl47-editor');
    var post_id = $f.find('[name=post_id]').val();
    var mw = parseInt($f.find('[name=img_max_w]').val() || 0, 10);
    var mh = parseInt($f.find('[name=img_max_h]').val() || 0, 10);
    $.post(ajaxUrl(), {action:'tpl47_resize_image', nonce:getNonce($m), post_id:post_id, img_max_w:mw, img_max_h:mh}, function(resp){
      if(resp && resp.success){
        if(resp.data.thumb){
          $f.find('.tpl47-preview').attr('src', resp.data.thumb).show();
          $f.find('.tpl47-remove-image').show().data('post_id', post_id);
        }
      } else {
        alert((resp && resp.data && resp.data.message) || 'Skalierung fehlgeschlagen.');
      }
    });
  });

  $(document).on('submit', 'form.tpl47-editor', function(e){
    e.preventDefault();
    var $f = $(this), $m = $f.closest('.tpl47-modal');
    var fd = new FormData(this);
    fd.append('action','tpl47_save'); fd.append('nonce',getNonce($m));
    $.ajax({ url:ajaxUrl(), method:'POST', data:fd, processData:false, contentType:false,
      success:function(resp){ if(resp && resp.success){ location.reload(); } else { alert((resp && resp.data && resp.data.message) || 'Speichern fehlgeschlagen.'); } },
      error:function(){ alert('Speichern fehlgeschlagen.'); }
    });
  });

  $(document).on('click', '.tpl47-del', function(e){
    e.preventDefault();
    if(!confirm('Eintrag wirklich löschen?')) return;
    var $li = $(this).closest('li'), id = $li.data('id'), $m = $(this).closest('.tpl47-modal');
    $.post(ajaxUrl(), {action:'tpl47_delete', nonce:getNonce($m), id:id}, function(resp){
      if(resp && resp.success){ $li.remove(); } else { alert((resp && resp.data && resp.data.message) || 'Löschen fehlgeschlagen.'); }
    });
  });
});