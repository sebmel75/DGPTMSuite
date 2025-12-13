/**
 * Zoho Books Integration - Frontend JavaScript
 * Handles table sorting and year filtering
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Year filter function
        function applyYearFilter() {
            const selectedYear = $('#year-filter').val();
            const $rows = $('#transactions-table tbody tr');

            if (selectedYear === '') {
                // Show all rows
                $rows.removeClass('hidden').show();
            } else {
                // Filter by year
                $rows.each(function() {
                    const rowYear = $(this).data('year');
                    if (rowYear == selectedYear) {
                        $(this).removeClass('hidden').show();
                    } else {
                        $(this).addClass('hidden').hide();
                    }
                });
            }
        }

        // Apply filter on change
        $('#year-filter').on('change', applyYearFilter);

        // Table sorting
        let currentSort = {
            column: null,
            direction: 'asc'
        };

        $('.zoho-books-transactions thead th.sortable').on('click', function() {
            const $th = $(this);
            const column = $th.data('sort');
            const $table = $th.closest('table');
            const $tbody = $table.find('tbody');
            // Sort ALL rows, not just visible ones
            const $rows = $tbody.find('tr').toArray();

            // Determine sort direction
            if (currentSort.column === column) {
                currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
            } else {
                currentSort.column = column;
                currentSort.direction = 'asc';
            }

            // Update header indicators
            $table.find('thead th').removeClass('sorted-asc sorted-desc');
            $th.addClass('sorted-' + currentSort.direction);

            // Sort rows
            $rows.sort(function(a, b) {
                const $a = $(a);
                const $b = $(b);

                let aVal, bVal;

                // Get values based on column
                if (column === 'date' || column === 'total' || column === 'balance') {
                    // Numeric sorting
                    aVal = parseFloat($a.find('td').eq(getColumnIndex(column)).data('sort-value'));
                    bVal = parseFloat($b.find('td').eq(getColumnIndex(column)).data('sort-value'));

                    if (isNaN(aVal)) aVal = 0;
                    if (isNaN(bVal)) bVal = 0;
                } else {
                    // Text sorting
                    aVal = $a.find('td').eq(getColumnIndex(column)).data('sort-value') ||
                           $a.find('td').eq(getColumnIndex(column)).text().trim();
                    bVal = $b.find('td').eq(getColumnIndex(column)).data('sort-value') ||
                           $b.find('td').eq(getColumnIndex(column)).text().trim();

                    aVal = aVal.toString().toLowerCase();
                    bVal = bVal.toString().toLowerCase();
                }

                // Compare
                if (currentSort.direction === 'asc') {
                    return aVal > bVal ? 1 : aVal < bVal ? -1 : 0;
                } else {
                    return aVal < bVal ? 1 : aVal > bVal ? -1 : 0;
                }
            });

            // Re-append sorted rows
            $.each($rows, function(index, row) {
                $tbody.append(row);
            });

            // Re-apply year filter after sorting
            applyYearFilter();
        });

        /**
         * Get column index by column name
         */
        function getColumnIndex(columnName) {
            const columnMap = {
                'type': 0,
                'number': 1,
                'date': 2,
                'total': 3,
                'balance': 4,
                'status': 5
            };
            return columnMap[columnName] || 0;
        }

        // Initial sort by date (newest first), then apply filter
        $('.zoho-books-transactions thead th[data-sort="date"]').trigger('click').trigger('click');

        // Apply year filter after initial sort to show only current year
        applyYearFilter();
    });

})(jQuery);
