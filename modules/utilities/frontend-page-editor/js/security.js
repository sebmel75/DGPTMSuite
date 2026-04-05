/**
 * DGPTM Frontend Page Editor - Security Script
 * Blocks navigation to unauthorized admin pages during edit session
 * Sends heartbeat to extend session automatically
 */
jQuery(document).ready(function($) {

    // Block admin link clicks (except editor-related)
    $('a').on('click', function(e) {
        var $link = $(this);

        // Elementor panel elements: always allow
        if ($link.closest('#elementor-panel, .elementor-panel, #elementor-navigator, .elementor-element, .elementor-editor-active, .e-route-panel').length > 0) {
            return;
        }

        var href = $link.attr('href');
        if (!href || href === '#' || href.indexOf('#') === 0) {
            return;
        }

        var allowed = ['post.php', 'admin-ajax.php', 'async-upload.php', 'media-upload.php', 'elementor'];
        var isAllowed = false;

        for (var i = 0; i < allowed.length; i++) {
            if (href.indexOf(allowed[i]) !== -1) {
                isAllowed = true;
                break;
            }
        }

        // External and frontend links: allow
        if (href.indexOf('wp-admin') === -1) {
            isAllowed = true;
        }

        if (!isAllowed) {
            e.preventDefault();
            alert('Navigation im Admin-Bereich ist eingeschraenkt. Sie koennen nur Ihre zugewiesene Seite bearbeiten.');
            return false;
        }
    });

    // Heartbeat: extend session on activity
    $(document).on('heartbeat-send', function(e, data) {
        data.dgptm_fpe_heartbeat = true;
    });

    $(document).on('heartbeat-tick', function(e, data) {
        if (data.dgptm_fpe_session && data.dgptm_fpe_session.expired) {
            alert('Ihre Bearbeitungssitzung ist abgelaufen. Bitte starten Sie die Bearbeitung erneut.');
            window.location.href = window.dgptmFpeHomeUrl || '/';
        }
    });
});
