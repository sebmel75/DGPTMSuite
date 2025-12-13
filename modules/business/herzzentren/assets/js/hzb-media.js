
(function($){
    let frames = {};

    function openMediaFrame(key, onSelect){
        if(frames[key]){
            frames[key].off('select');
            frames[key].on('select', function(){
                var attachment = frames[key].state().get('selection').first().toJSON();
                onSelect(attachment);
            });
            frames[key].open();
            return;
        }
        let libProps = { type: 'image' };
        if (typeof HZB_MEDIA !== 'undefined' && HZB_MEDIA.currentUserId){
            libProps.author = HZB_MEDIA.currentUserId; // Clientseitige Filterung
            libProps.hzb_media = 1;                    // Serverseitige Absicherung (Marker)
        }
        frames[key] = wp.media({
            title: 'Bild wählen/hochladen',
            button: { text: 'Übernehmen' },
            multiple: false,
            library: libProps
        });
        frames[key].on('select', function(){
            var attachment = frames[key].state().get('selection').first().toJSON();
            onSelect(attachment);
        });
        frames[key].open();
    }

    function setImage(fieldBaseId, attachment){
        var idInput = $('#'+fieldBaseId);
        var preview = $('#'+fieldBaseId+'-preview');
        var remove  = $('#'+fieldBaseId+'-remove');
        var url = (attachment.sizes && attachment.sizes.medium && attachment.sizes.medium.url) ? attachment.sizes.medium.url : attachment.url;
        idInput.val(attachment.id);
        preview.attr('src', url).css('display','inline-block').attr('alt', attachment.alt || 'Ausgewähltes Bild');
        remove.show();
    }

    function clearImage(fieldBaseId){
        var idInput = $('#'+fieldBaseId);
        var preview = $('#'+fieldBaseId+'-preview');
        var remove  = $('#'+fieldBaseId+'-remove');
        idInput.val('');
        preview.attr('src','').hide();
        remove.hide();
    }

    $(document).on('click', '#team-bild-upload', function(e){
        e.preventDefault();
        if (typeof wp === 'undefined' || !wp.media) { return; }
        openMediaFrame('team-bild', function(attachment){
            setImage('team-bild', attachment);
        });
    });

    $(document).on('click', '#klinik-logo-upload', function(e){
        e.preventDefault();
        if (typeof wp === 'undefined' || !wp.media) { return; }
        openMediaFrame('klinik-logo', function(attachment){
            setImage('klinik-logo', attachment);
        });
    });

    $(document).on('click', '#team-bild-remove', function(e){
        e.preventDefault();
        clearImage('team-bild');
    });

    $(document).on('click', '#klinik-logo-remove', function(e){
        e.preventDefault();
        clearImage('klinik-logo');
    });

})(jQuery);
