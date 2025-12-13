/**
 * Kardiotechnik Archiv - Frontend JavaScript
 */

(function($) {
    'use strict';

    // Alle Frontend-Funktionen werden direkt im Template gehandhabt
    // Diese Datei kann für zukünftige Erweiterungen verwendet werden

    $(document).ready(function() {
        // Enter-Taste im Suchfeld
        $('#kta-search-input').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                $('#kta-search-form').submit();
            }
        });

        // Jahr-Validierung
        $('#kta-year-from').on('change', function() {
            var yearFrom = parseInt($(this).val());
            var yearTo = parseInt($('#kta-year-to').val());

            if (yearFrom > yearTo) {
                $('#kta-year-to').val($(this).val());
            }
        });

        $('#kta-year-to').on('change', function() {
            var yearFrom = parseInt($('#kta-year-from').val());
            var yearTo = parseInt($(this).val());

            if (yearTo < yearFrom) {
                $('#kta-year-from').val($(this).val());
            }
        });
    });

})(jQuery);
