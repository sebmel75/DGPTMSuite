jQuery(function($) {
    var $d = $('.dgptm-dash');
    if (!$d.length) return;

    var loaded = {};
    loaded[$d.data('active')] = true;

    // Main tabs
    $d.on('click', '.dgptm-nav-item', function(e) {
        e.preventDefault();
        var id = $(this).data('tab');
        $d.find('.dgptm-nav-item').removeClass('dgptm-nav-active');
        $(this).addClass('dgptm-nav-active');
        $d.find('.dgptm-panel').hide();
        var $p = $d.find('[data-panel="' + id + '"]').show();

        if (!loaded[id]) {
            loadTab(id, $p);
            loaded[id] = true;
        }
    });

    // Folder sub-tabs
    $d.on('click', '.dgptm-ftab', function(e) {
        e.preventDefault();
        var id = $(this).data('ftab');
        var $folder = $(this).closest('.dgptm-folder');
        $folder.find('.dgptm-ftab').removeClass('dgptm-ftab-active');
        $(this).addClass('dgptm-ftab-active');
        $folder.find('.dgptm-fpanel').hide();
        var $fp = $folder.find('[data-fpanel="' + id + '"]').show();

        if (!loaded[id]) {
            loadTab(id, $fp);
            loaded[id] = true;
        }
    });

    // Deep link
    var hash = location.hash.replace('#tab-', '');
    if (hash) {
        var $tab = $d.find('.dgptm-nav-item[data-tab="' + hash + '"]');
        if ($tab.length) $tab.trigger('click');
    }

    function loadTab(id, $target) {
        $target.html('<div class="dgptm-loading">Wird geladen...</div>');
        $.post(dgptmDash.ajax, {
            action: 'dgptm_dash_load_tab',
            nonce: dgptmDash.nonce,
            tab: id
        }).done(function(r) {
            if (r.success) {
                $target.html(r.data.html);
                // Execute inline scripts
                $target.find('script').each(function() {
                    var s = document.createElement('script');
                    if (this.src) s.src = this.src; else s.textContent = this.textContent;
                    document.body.appendChild(s);
                    $(this).remove();
                });
            } else {
                $target.html('<p style="color:red">' + (r.data || 'Fehler') + '</p>');
            }
        }).fail(function() {
            $target.html('<p style="color:red">Laden fehlgeschlagen</p>');
        });
    }
});
