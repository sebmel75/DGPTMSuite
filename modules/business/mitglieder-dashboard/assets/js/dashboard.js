jQuery(function($) {
    var $d = $('.dgptm-dash');
    if (!$d.length) return;

    var loaded = {};
    loaded[$d.data('active')] = true;

    // Gemeinsame Tab-Wechsel-Logik
    function switchTab(id) {
        $d.find('.dgptm-nav-item').removeClass('dgptm-nav-active');
        $d.find('.dgptm-nav-item[data-tab="' + id + '"]').addClass('dgptm-nav-active');
        $d.find('.dgptm-panel').hide();
        var $p = $d.find('[data-panel="' + id + '"]').show();
        // Mobile-Dropdown synchronisieren
        $('#dgptm-nav-select').val(id);

        if (!loaded[id]) {
            loadTab(id, $p);
            loaded[id] = true;
        }
    }

    // Desktop: Tab-Klick
    $d.on('click', '.dgptm-nav-item[data-tab]', function(e) {
        e.preventDefault();
        switchTab($(this).data('tab'));
    });

    // Mobile: Dropdown-Wechsel
    $(document).on('change', '#dgptm-nav-select', function() {
        switchTab($(this).val());
    });

    // Folder sub-tabs (skip link tabs)
    $d.on('click', '.dgptm-ftab[data-ftab]', function(e) {
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

    // Deep link: #tab-name oder ?tab=name
    var deepTab = '';
    var hash = location.hash.replace('#tab-', '');
    if (hash) deepTab = hash;
    // GET-Parameter ?tab=name hat Vorrang
    var urlParams = new URLSearchParams(location.search);
    var tabParam = urlParams.get('tab');
    if (tabParam) deepTab = tabParam;

    if (deepTab) {
        // Erst Top-Level Tab prüfen
        var $tab = $d.find('.dgptm-nav-item[data-tab="' + deepTab + '"]');
        if ($tab.length) {
            $tab.trigger('click');
        } else {
            // Sub-Tab: Eltern-Tab finden und öffnen, dann Sub-Tab
            var $ftab = $d.find('.dgptm-ftab[data-ftab="' + deepTab + '"]');
            if ($ftab.length) {
                var $panel = $ftab.closest('.dgptm-panel');
                var parentId = $panel.data('panel');
                if (parentId) {
                    $d.find('.dgptm-nav-item[data-tab="' + parentId + '"]').trigger('click');
                    setTimeout(function(){ $ftab.trigger('click'); }, 300);
                }
            }
        }
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
                // Notify other modules that tab content was loaded
                $(document).trigger('dgptm_tab_loaded', [id]);
            } else {
                $target.html('<p style="color:red">' + (r.data || 'Fehler') + '</p>');
            }
        }).fail(function() {
            $target.html('<p style="color:red">Laden fehlgeschlagen</p>');
        });
    }
});
