jQuery(function($) {
    var $d = $('.dgptm-dash');
    console.log('[Dashboard] Init, container found:', $d.length, 'active:', $d.data('active'));
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

        $(document).trigger('dgptm:ftab-switched', { panel: id });
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
        console.log('[Dashboard] loadTab:', id);
        $target.html('<div class="dgptm-loading">Wird geladen...</div>');
        $.ajax({
            url: dgptmDash.ajax,
            type: 'POST',
            dataType: 'text',
            data: { action: 'dgptm_dash_load_tab', nonce: dgptmDash.nonce, tab: id }
        }).done(function(raw) {
            console.log('[Dashboard] RAW response for "' + id + '" (len=' + raw.length + '):', raw.substring(0, 300));

            // "0" = WordPress AJAX handler nicht gefunden (Session abgelaufen)
            if (raw === '0' || raw === '') {
                $target.html('<p style="padding:20px;text-align:center;color:#666;">Ihre Sitzung ist abgelaufen. <a href="' + window.location.href + '" style="color:#0073aa;">Seite neu laden</a> oder <a href="/wp-login.php?redirect_to=' + encodeURIComponent(window.location.href) + '" style="color:#0073aa;">erneut anmelden</a>.</p>');
                return;
            }

            var r;
            try { r = JSON.parse(raw); } catch(e) {
                console.error('[Dashboard] JSON parse failed:', e.message, 'Raw:', raw.substring(0, 500));
                $target.html('<p style="color:red;padding:12px;">Server-Antwort ungueltig. <a href="javascript:location.reload()">Seite neu laden</a></p>');
                return;
            }
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
                $(document).trigger('dgptm:ftab-switched', { panel: id });
            } else {
                var msg = (typeof r.data === 'string') ? r.data : (r.data && r.data.message ? r.data.message : 'Unbekannter Fehler');
                if (msg.indexOf('Sitzung') !== -1 || msg.indexOf('nonce') !== -1) {
                    msg += ' <a href="javascript:location.reload()" style="color:#0073aa;text-decoration:underline">Seite neu laden</a>';
                }
                $target.html('<p style="color:red;padding:12px;">' + msg + '</p>');
            }
        }).fail(function(xhr) {
            var raw = xhr.responseText || '';
            var failMsg = 'Verbindungsfehler (HTTP ' + xhr.status + ')';
            if (xhr.status === 403 || raw.indexOf('nonce') !== -1) {
                failMsg = 'Sitzung abgelaufen. <a href="javascript:location.reload()" style="color:#0073aa;text-decoration:underline">Seite neu laden</a>';
            } else if (xhr.status === 0) {
                failMsg = 'Keine Verbindung zum Server.';
            } else if (raw.length > 0 && raw.charAt(0) !== '{') {
                // PHP-Warnung oder HTML vor dem JSON
                failMsg = 'Server-Fehler. <a href="javascript:location.reload()" style="color:#0073aa;text-decoration:underline">Seite neu laden</a>';
                console.error('Dashboard AJAX raw response:', raw.substring(0, 500));
            }
            $target.html('<p style="color:red;padding:12px;">' + failMsg + '</p>');
        });
    }
});
