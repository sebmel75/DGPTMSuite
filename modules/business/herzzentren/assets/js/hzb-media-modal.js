
(function($){
    let currentField = null; // 'team-bild' or 'klinik-logo'
    let currentHzId = null;
    let selected = null;

    function openModal(hzId, field){
        currentField = field;
        currentHzId = hzId;
        selected = null;
        $('#hzb-media-overlay, #hzb-media-modal').show();
        $('.hzb-tab').removeClass('active');
        $('#hzb-tab-existing').addClass('active');
        $('#hzb-existing').removeClass('hzb-hidden');
        $('#hzb-upload').addClass('hzb-hidden');
        $('#hzb-error').empty();

        loadExisting();
    }

    function closeModal(){
        $('#hzb-media-overlay, #hzb-media-modal').hide();
        $('#hzb-grid').empty();
        $('#hzb-file').val('');
        $('#hzb-error').empty();
        selected = null;
    }

    function loadExisting(){
        $('#hzb-grid').empty().append('<p>Wird geladen…</p>');
        $.ajax({
            url: HZB_MEDIA.ajaxUrl,
            method: 'POST',
            data: {
                action: 'hzb_list_media_for_hz',
                security: HZB_MEDIA.listNonce,
                herzzentrum_id: currentHzId
            }
        }).done(function(resp){
            $('#hzb-grid').empty();
            if(!resp || !resp.success || !resp.data || !resp.data.length){
                $('#hzb-grid').append('<p>Keine Bilder vorhanden. Wechsle auf „Neu hochladen“.</p>');
                return;
            }
            resp.data.forEach(function(item){
                const card = $('<div class="hzb-card" data-id="'+item.id+'" title="Auswählen"></div>');
                card.append('<img loading="lazy" src="'+(item.thumbnail||item.url)+'" alt="">');
                card.append('<div style="font-size:12px;word-break:break-all">'+(item.filename||('Bild #'+item.id))+'</div>');
                card.on('click', function(){
                    $('.hzb-card').removeClass('selected').css('outline','none');
                    $(this).addClass('selected').css('outline','2px solid #0073aa');
                    selected = item;
                });
                $('#hzb-grid').append(card);
            });
        }).fail(function(){
            $('#hzb-grid').html('<p class="hzb-error">Fehler beim Laden der Bilder.</p>');
        });
    }

    function applySelection(item){
        if(!item){ return; }
        const idInput = $('#'+currentField);
        const preview = $('#'+currentField+'-preview');
        const removeBtn = $('#'+currentField+'-remove');
        const url = item.medium || item.thumbnail || item.url;
        idInput.val(item.id);
        preview.attr('src', url).show();
        removeBtn.show();
        closeModal();
    }

    function uploadFiles(files){
        if(!files || !files.length) return;
        $('#hzb-error').empty();
        const fd = new FormData();
        fd.append('action', 'hzb_upload_media_for_hz');
        fd.append('security', HZB_MEDIA.uploadNonce);
        fd.append('herzzentrum_id', currentHzId);
        fd.append('field', currentField);
        fd.append('file', files[0]);
        $('#hzb-upload-status').text('Upload läuft…');
        $.ajax({
            url: HZB_MEDIA.ajaxUrl,
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false
        }).done(function(resp){
            $('#hzb-upload-status').text('');
            if(!resp || !resp.success){
                $('#hzb-error').text(resp && resp.data ? resp.data : 'Upload fehlgeschlagen.');
                return;
            }
            // Rückgabe enthält das neue Attachment (bereits angehangen)
            const item = resp.data;
            applySelection(item);
        }).fail(function(){
            $('#hzb-upload-status').text('');
            $('#hzb-error').text('Upload fehlgeschlagen.');
        });
    }

    // Open hooks
    $(document).on('click', '#team-bild-upload', function(e){
        e.preventDefault();
        const hzId = $('#herzzentrum_id').val();
        openModal(hzId, 'team-bild');
    });
    $(document).on('click', '#klinik-logo-upload', function(e){
        e.preventDefault();
        const hzId = $('#herzzentrum_id').val();
        openModal(hzId, 'klinik-logo');
    });

    // Remove
    $(document).on('click', '#team-bild-remove', function(e){
        e.preventDefault();
        $('#team-bild').val('');
        $('#team-bild-preview').attr('src','').hide();
        $('#team-bild-remove').hide();
    });
    $(document).on('click', '#klinik-logo-remove', function(e){
        e.preventDefault();
        $('#klinik-logo').val('');
        $('#klinik-logo-preview').attr('src','').hide();
        $('#klinik-logo-remove').hide();
    });

    // Tabs
    $(document).on('click', '#hzb-tab-existing', function(){
        $('.hzb-tab').removeClass('active'); $(this).addClass('active');
        $('#hzb-existing').removeClass('hzb-hidden');
        $('#hzb-upload').addClass('hzb-hidden');
        loadExisting();
    });
    $(document).on('click', '#hzb-tab-upload', function(){
        $('.hzb-tab').removeClass('active'); $(this).addClass('active');
        $('#hzb-existing').addClass('hzb-hidden');
        $('#hzb-upload').removeClass('hzb-hidden');
    });

    // Upload handlers
    $(document).on('change', '#hzb-file', function(){
        uploadFiles(this.files);
    });
    $(document).on('dragover', '#hzb-drop', function(e){
        e.preventDefault(); e.stopPropagation(); $(this).addClass('dragover');
    });
    $(document).on('dragleave', '#hzb-drop', function(e){
        e.preventDefault(); e.stopPropagation(); $(this).removeClass('dragover');
    });
    $(document).on('drop', '#hzb-drop', function(e){
        e.preventDefault(); e.stopPropagation(); $(this).removeClass('dragover');
        const files = e.originalEvent.dataTransfer.files;
        uploadFiles(files);
    });

    // Modal buttons
    $(document).on('click', '#hzb-cancel', function(){ closeModal(); });
    $(document).on('click', '#hzb-use', function(){
        if(selected){ applySelection(selected); }
        else { $('#hzb-error').text('Bitte zuerst ein Bild auswählen.'); }
    });
    $(document).on('click', '#hzb-media-overlay', function(){ closeModal(); });

})(jQuery);
