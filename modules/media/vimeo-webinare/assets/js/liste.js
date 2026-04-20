/* Liste — Suche + Status-Filter + Zertifikat-Download */
(function ($) {
    'use strict';

    function applyFilters($root) {
        var q = ($root.find('.dgptm-vw-liste-search').val() || '').trim().toLowerCase();
        var status = $root.find('.dgptm-vw-liste-status').val() || 'all';

        $root.find('.dgptm-vw-webinar-card').each(function () {
            var t = ($(this).data('title') || '').toString();
            var s = $(this).data('status') || 'not-started';
            var matchSearch = !q || t.indexOf(q) !== -1;
            var matchStatus = status === 'all' || s === status;
            $(this).toggle(matchSearch && matchStatus);
        });
    }

    $(function () {
        var $root = $('.dgptm-vw-liste');
        if (!$root.length) return;

        $root.on('input', '.dgptm-vw-liste-search', function () { applyFilters($root); });
        $root.on('change', '.dgptm-vw-liste-status', function () { applyFilters($root); });

        $root.on('click', '.dgptm-vw-certificate', function () {
            var id = $(this).data('webinar-id');
            if (!id) return;
            // Bestehende vwData-Konfiguration der force_enqueue_assets() wiederverwenden
            var cfg = window.vwData || {};
            var ajaxUrl = cfg.ajaxUrl || '/wp-admin/admin-ajax.php';
            var nonce = cfg.nonce || '';
            $.post(ajaxUrl, { action: 'vw_generate_certificate', webinar_id: id, nonce: nonce })
             .then(function (resp) {
                 if (resp && resp.success && resp.data && resp.data.url) {
                     window.open(resp.data.url, '_blank');
                 } else {
                     window.alert((resp && resp.data) || 'Zertifikat konnte nicht erzeugt werden.');
                 }
             });
        });
    });
})(jQuery);
