/* Statistiken — client-seitige Sortierung der Performance-Tabelle */
(function () {
    'use strict';

    function getSortValue($row, key) {
        var el = $row.get(0);
        if (!el) return null;
        if (key === 'title') return (el.dataset.title || '').toLowerCase();
        var raw = el.dataset[key];
        return raw !== undefined ? parseFloat(raw) : 0;
    }

    function sortBy(table, key, direction) {
        var $tbody = jQuery(table).find('tbody');
        var rows = $tbody.find('tr').toArray();
        rows.sort(function (a, b) {
            var va = getSortValue(jQuery(a), key);
            var vb = getSortValue(jQuery(b), key);
            if (va < vb) return direction === 'asc' ? -1 : 1;
            if (va > vb) return direction === 'asc' ? 1 : -1;
            return 0;
        });
        rows.forEach(function (r) { $tbody.append(r); });
    }

    jQuery(function ($) {
        $('.dgptm-vw-stats-table thead th[data-sort]').on('click', function () {
            var key = $(this).data('sort');
            var $th = $(this);
            var $table = $th.closest('table');
            var direction = $th.hasClass('is-sorted-asc') ? 'desc' : 'asc';
            $table.find('thead th').removeClass('is-sorted-asc is-sorted-desc');
            $th.addClass(direction === 'asc' ? 'is-sorted-asc' : 'is-sorted-desc');
            sortBy($table, key, direction);
        });
    });
})();
