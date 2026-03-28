/**
 * DGPTM Session Display - Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        // Sponsor hinzufügen (bereits im HTML implementiert)

        // Raum-Zuordnung Auto-Map Feedback
        $('button[name="dgptm_auto_map_rooms"]').on('click', function() {
            $(this).prop('disabled', true).text('Zuordnung läuft...');
        });

        // Verbindungstest Feedback
        $('button[name="dgptm_session_display_test_connection"]').on('click', function() {
            $(this).prop('disabled', true).text('Teste Verbindung...');
        });

        // Sessions aktualisieren Feedback
        $('button[name="dgptm_session_display_refresh_sessions"]').on('click', function() {
            $(this).prop('disabled', true).text('Aktualisiere Sessions...');
        });
    });

})(jQuery);
