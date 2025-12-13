<?php
/**
 * Plugin Name: DGPTM - Fortbildung Statistik Add-on
 * Plugin URI:  https://example.com
 * Description: Zeigt umfangreiche Statistiken zu Fortbildungen an: Fortbildungen pro Jahr, Durchschnitt pro Mitglied, Teilnehmer pro Veranstaltung, etc. Verwendung: [fortbildung_statistik]
 * Version:     1.2
 * Author:      Seb
 * Author URI:  https://example.com
 * License:     GPL2
 * Requires:    DGPTM - Fortbildung Liste und Quiz Importer Plugin
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Shortcode: [fortbildung_statistik]
 * 
 * Optional Parameter:
 * - default_years="5" - Anzahl der Jahre die standardmäßig angezeigt werden
 * 
 * Charts sind standardmäßig aktiviert und können über einen Toggle-Button 
 * im Frontend ein- und ausgeschaltet werden.
 */
add_shortcode( 'fortbildung_statistik', 'dgptm_fortbildung_statistik_shortcode' );

function dgptm_fortbildung_statistik_shortcode( $atts ) {
    // Parameter
    $atts = shortcode_atts( array(
        'default_years' => '5',
    ), $atts );

    $default_years = max( 1, intval( $atts['default_years'] ) );

    ob_start();
    ?>
    <div class="fobi-stats-container">
        <style>
        .fobi-stats-container {
            max-width: 1200px;
            margin: 0 auto;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, sans-serif;
        }
        .fobi-stats-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 24px;
            border-radius: 8px 8px 0 0;
            margin-bottom: 24px;
        }
        .fobi-stats-header h2 {
            margin: 0 0 8px 0;
            font-size: 28px;
            font-weight: 600;
        }
        .fobi-stats-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 14px;
        }
        .fobi-stats-filters {
            background: #f8f9fa;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            align-items: center;
        }
        .fobi-stats-filters label {
            font-weight: 600;
            margin-right: 8px;
        }
        .fobi-stats-filters select,
        .fobi-stats-filters button {
            padding: 8px 16px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .fobi-stats-filters button {
            background: #667eea;
            color: white;
            border: none;
            cursor: pointer;
            transition: background 0.3s;
        }
        .fobi-stats-filters button:hover {
            background: #5568d3;
        }
        .fobi-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }
        .fobi-stats-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .fobi-stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .fobi-stats-card-title {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        .fobi-stats-card-value {
            font-size: 36px;
            font-weight: 700;
            color: #333;
            margin-bottom: 4px;
        }
        .fobi-stats-card-subtitle {
            font-size: 12px;
            color: #999;
        }
        .fobi-stats-section {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 24px;
        }
        .fobi-stats-section h3 {
            margin: 0 0 16px 0;
            font-size: 20px;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 8px;
        }
        .fobi-stats-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }
        .fobi-stats-table th,
        .fobi-stats-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .fobi-stats-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .fobi-stats-table tr:hover {
            background: #f8f9fa;
        }
        .fobi-stats-table td {
            font-size: 14px;
        }
        .fobi-stats-chart {
            margin-top: 20px;
            height: 300px;
        }
        .fobi-stats-loading {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        .fobi-stats-empty {
            text-align: center;
            padding: 40px;
            color: #999;
            font-style: italic;
        }
        .fobi-stats-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        .fobi-stats-badge-primary {
            background: #e3f2fd;
            color: #1976d2;
        }
        .fobi-stats-badge-success {
            background: #e8f5e9;
            color: #388e3c;
        }
        .fobi-stats-badge-warning {
            background: #fff3e0;
            color: #f57c00;
        }
        .fobi-stats-chart-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: white;
            border: 2px solid #667eea;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
            user-select: none;
        }
        .fobi-stats-chart-toggle:hover {
            background: #f8f9fa;
        }
        .fobi-stats-chart-toggle input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        .fobi-stats-chart-toggle label {
            margin: 0;
            cursor: pointer;
            font-weight: 600;
            color: #667eea;
        }
        .fobi-stats-chart-container {
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 2px dashed #ddd;
        }
        .fobi-stats-chart-container.hidden {
            display: none;
        }
        .fobi-stats-chart {
            max-width: 600px;
            margin: 0 auto;
            height: 350px;
        }
        
        /* DataTables Styling */
        .dataTables_wrapper {
            padding: 0 !important;
        }
        .dataTables_wrapper .dt-buttons {
            margin-bottom: 12px;
        }
        .dt-button {
            background: #667eea !important;
            color: white !important;
            border: none !important;
            padding: 8px 16px !important;
            border-radius: 4px !important;
            cursor: pointer !important;
            font-size: 14px !important;
            transition: background 0.3s !important;
        }
        .dt-button:hover {
            background: #5568d3 !important;
        }
        .dataTables_filter input {
            border: 1px solid #ddd;
            padding: 6px 12px;
            border-radius: 4px;
            margin-left: 8px;
        }
        .dataTables_length select {
            border: 1px solid #ddd;
            padding: 4px 8px;
            border-radius: 4px;
            margin: 0 8px;
        }
        table.dataTable thead th {
            background: #f8f9fa;
            font-weight: 600;
            border-bottom: 2px solid #667eea !important;
        }
        table.dataTable tbody tr:hover {
            background: #f8f9fa;
        }
        .dataTables_info,
        .dataTables_paginate {
            margin-top: 16px;
            font-size: 13px;
        }
        .paginate_button {
            padding: 4px 10px !important;
            margin: 0 2px !important;
            border: 1px solid #ddd !important;
            border-radius: 4px !important;
            background: white !important;
        }
        .paginate_button.current {
            background: #667eea !important;
            color: white !important;
            border-color: #667eea !important;
        }
        .paginate_button:hover:not(.disabled) {
            background: #f8f9fa !important;
            border-color: #667eea !important;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .fobi-stats-grid {
                grid-template-columns: 1fr;
            }
            .fobi-stats-table {
                font-size: 12px;
            }
            .fobi-stats-table th,
            .fobi-stats-table td {
                padding: 8px;
            }
            .fobi-stats-filters {
                flex-direction: column;
                align-items: stretch;
            }
            .fobi-stats-header h2 {
                font-size: 22px;
            }
            /* DataTables Mobile */
            .dataTables_wrapper .dataTables_length,
            .dataTables_wrapper .dataTables_filter {
                text-align: left;
                margin-bottom: 8px;
            }
            table.dataTable {
                font-size: 12px;
            }
        }
        </style>

        <div class="fobi-stats-header">
            <h2>📊 Fortbildungs-Statistiken</h2>
            <p>Übersicht über alle Fortbildungsaktivitäten</p>
        </div>

        <div class="fobi-stats-filters">
            <div>
                <label for="fobi-stats-year-from">Von Jahr:</label>
                <select id="fobi-stats-year-from">
                    <?php
                    $current_year = date('Y');
                    for ( $y = $current_year; $y >= $current_year - 10; $y-- ) {
                        $selected = ( $y == $current_year - $default_years + 1 ) ? ' selected' : '';
                        echo '<option value="' . esc_attr($y) . '"' . $selected . '>' . esc_html($y) . '</option>';
                    }
                    ?>
                </select>
            </div>
            
            <div>
                <label for="fobi-stats-year-to">Bis Jahr:</label>
                <select id="fobi-stats-year-to">
                    <?php
                    for ( $y = $current_year; $y >= $current_year - 10; $y-- ) {
                        $selected = ( $y == $current_year ) ? ' selected' : '';
                        echo '<option value="' . esc_attr($y) . '"' . $selected . '>' . esc_html($y) . '</option>';
                    }
                    ?>
                </select>
            </div>
            
            <div>
                <label>
                    <input type="checkbox" id="fobi-stats-only-approved" checked>
                    Nur freigegebene
                </label>
            </div>
            
            <div class="fobi-stats-chart-toggle">
                <input type="checkbox" id="fobi-stats-show-charts" checked>
                <label for="fobi-stats-show-charts">📊 Diagramme anzeigen</label>
            </div>
            
            <button id="fobi-stats-reload" class="button">Aktualisieren</button>
        </div>

        <div id="fobi-stats-content">
            <div class="fobi-stats-loading">Lade Statistiken...</div>
        </div>
    </div>

    <!-- Zuerst Chart.js laden -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <!-- Dann DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.colVis.min.js"></script>

    <script>
    (function($) {
        if (typeof window.ajaxurl === 'undefined') {
            window.ajaxurl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
        }

        // Warte bis Chart.js geladen ist
        function waitForChart(callback, attempts) {
            attempts = attempts || 0;
            if (typeof Chart !== 'undefined') {
                console.log('Chart.js erfolgreich geladen!');
                callback();
            } else if (attempts < 50) {
                console.log('Warte auf Chart.js... Versuch ' + (attempts + 1));
                setTimeout(function() {
                    waitForChart(callback, attempts + 1);
                }, 100);
            } else {
                console.error('Chart.js konnte nicht geladen werden!');
            }
        }

        // Global Charts speichern für Cleanup
        var chartInstances = {};

        // Chart-Anzeige-Status aus localStorage laden (Standard: an)
        var showCharts = localStorage.getItem('fobi_show_charts') !== 'false';
        $('#fobi-stats-show-charts').prop('checked', showCharts);

        function loadStats() {
            var yearFrom = $('#fobi-stats-year-from').val();
            var yearTo = $('#fobi-stats-year-to').val();
            var onlyApproved = $('#fobi-stats-only-approved').is(':checked');

            $('#fobi-stats-content').html('<div class="fobi-stats-loading">Lade Statistiken...</div>');

            $.ajax({
                url: window.ajaxurl,
                method: 'POST',
                data: {
                    action: 'dgptm_get_fortbildung_stats',
                    year_from: yearFrom,
                    year_to: yearTo,
                    only_approved: onlyApproved ? '1' : '0'
                },
                success: function(response) {
                    if (response.success && response.data) {
                        renderStats(response.data);
                    } else {
                        $('#fobi-stats-content').html('<div class="fobi-stats-empty">Fehler beim Laden der Statistiken.</div>');
                    }
                },
                error: function() {
                    $('#fobi-stats-content').html('<div class="fobi-stats-empty">Fehler beim Laden der Statistiken.</div>');
                }
            });
        }

        function renderStats(data) {
            var showCharts = $('#fobi-stats-show-charts').is(':checked');
            console.log('renderStats aufgerufen. showCharts:', showCharts);
            var html = '';

            // Kennzahlen-Karten
            html += '<div class="fobi-stats-grid">';
            html += '<div class="fobi-stats-card">';
            html += '<div class="fobi-stats-card-title">Gesamt Fortbildungen</div>';
            html += '<div class="fobi-stats-card-value">' + data.total_fortbildungen + '</div>';
            html += '<div class="fobi-stats-card-subtitle">im gewählten Zeitraum</div>';
            html += '</div>';

            html += '<div class="fobi-stats-card">';
            html += '<div class="fobi-stats-card-title">Teilnehmende Mitglieder</div>';
            html += '<div class="fobi-stats-card-value">' + data.unique_members + '</div>';
            html += '<div class="fobi-stats-card-subtitle">mit mind. 1 Fortbildung</div>';
            html += '</div>';

            html += '<div class="fobi-stats-card">';
            html += '<div class="fobi-stats-card-title">Ø pro teilnehmendem Mitglied</div>';
            html += '<div class="fobi-stats-card-value">' + data.avg_per_active_member + '</div>';
            html += '<div class="fobi-stats-card-subtitle">nur aktive Teilnehmer</div>';
            html += '</div>';

            html += '<div class="fobi-stats-card">';
            html += '<div class="fobi-stats-card-title">Ø pro Mitglied (gesamt)</div>';
            html += '<div class="fobi-stats-card-value">' + data.avg_per_all_members + '</div>';
            html += '<div class="fobi-stats-card-subtitle">alle ' + data.total_members + ' Benutzer</div>';
            html += '</div>';

            html += '<div class="fobi-stats-card">';
            html += '<div class="fobi-stats-card-title">Gesamt EBCP-Punkte</div>';
            html += '<div class="fobi-stats-card-value">' + data.total_points + '</div>';
            html += '<div class="fobi-stats-card-subtitle">alle Teilnehmer</div>';
            html += '</div>';

            html += '<div class="fobi-stats-card">';
            html += '<div class="fobi-stats-card-title">Bescheinigungen erstellt</div>';
            html += '<div class="fobi-stats-card-value">' + data.total_certificates + '</div>';
            html += '<div class="fobi-stats-card-subtitle">Nachweise generiert</div>';
            html += '</div>';
            html += '</div>';

            // Beteiligungsquote
            html += '<div class="fobi-stats-section" style="background: linear-gradient(135deg, #667eea22 0%, #764ba222 100%); border-color: #667eea;">';
            html += '<h3>📈 Beteiligungsquote</h3>';
            html += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">';
            
            var participationRate = data.total_members > 0 
                ? ((data.unique_members / data.total_members) * 100).toFixed(1)
                : '0.0';
            
            html += '<div style="padding: 16px; background: white; border-radius: 6px; text-align: center;">';
            html += '<div style="font-size: 14px; color: #666; margin-bottom: 8px;">Aktive Teilnehmer</div>';
            html += '<div style="font-size: 32px; font-weight: 700; color: #667eea;">' + data.unique_members + '</div>';
            html += '<div style="font-size: 12px; color: #999;">von ' + data.total_members + ' Mitgliedern</div>';
            html += '</div>';
            
            html += '<div style="padding: 16px; background: white; border-radius: 6px; text-align: center;">';
            html += '<div style="font-size: 14px; color: #666; margin-bottom: 8px;">Beteiligungsquote</div>';
            html += '<div style="font-size: 32px; font-weight: 700; color: #764ba2;">' + participationRate + '%</div>';
            html += '<div style="font-size: 12px; color: #999;">haben mind. 1 Fortbildung</div>';
            html += '</div>';
            
            var inactiveMembers = data.total_members - data.unique_members;
            html += '<div style="padding: 16px; background: white; border-radius: 6px; text-align: center;">';
            html += '<div style="font-size: 14px; color: #666; margin-bottom: 8px;">Nicht-Teilnehmer</div>';
            html += '<div style="font-size: 32px; font-weight: 700; color: #999;">' + inactiveMembers + '</div>';
            html += '<div style="font-size: 12px; color: #999;">keine Fortbildungen erfasst</div>';
            html += '</div>';
            
            html += '</div>';
            html += '</div>';

            // Fortbildungen pro Jahr
            html += '<div class="fobi-stats-section">';
            html += '<h3>Fortbildungen pro Jahr</h3>';
            if (data.per_year && data.per_year.length > 0) {
                if (showCharts) {
                    html += '<div class="fobi-stats-chart-container"><canvas id="fobi-chart-per-year" class="fobi-stats-chart"></canvas></div>';
                }
                html += '<table class="fobi-stats-table">';
                html += '<thead><tr><th>Jahr</th><th>Anzahl Fortbildungen</th><th>Teilnehmer</th><th>EBCP-Punkte</th></tr></thead>';
                html += '<tbody>';
                data.per_year.forEach(function(item) {
                    html += '<tr>';
                    html += '<td><strong>' + item.year + '</strong></td>';
                    html += '<td>' + item.count + '</td>';
                    html += '<td>' + item.members + '</td>';
                    html += '<td>' + item.points + '</td>';
                    html += '</tr>';
                });
                html += '</tbody></table>';
            } else {
                html += '<div class="fobi-stats-empty">Keine Daten verfügbar</div>';
            }
            html += '</div>';

            // Top Mitglieder
            html += '<div class="fobi-stats-section">';
            html += '<h3>Top 10 Mitglieder (nach Anzahl Fortbildungen)</h3>';
            if (data.top_members && data.top_members.length > 0) {
                html += '<table class="fobi-stats-table">';
                html += '<thead><tr><th>Rang</th><th>Name</th><th>Fortbildungen</th><th>EBCP-Punkte</th></tr></thead>';
                html += '<tbody>';
                data.top_members.forEach(function(item, index) {
                    html += '<tr>';
                    html += '<td><span class="fobi-stats-badge fobi-stats-badge-primary">#' + (index + 1) + '</span></td>';
                    html += '<td>' + item.name + '</td>';
                    html += '<td>' + item.count + '</td>';
                    html += '<td>' + item.points + '</td>';
                    html += '</tr>';
                });
                html += '</tbody></table>';
            } else {
                html += '<div class="fobi-stats-empty">Keine Daten verfügbar</div>';
            }
            html += '</div>';

            // Veranstaltungen (gruppiert nach VNR/Titel)
            html += '<div class="fobi-stats-section">';
            html += '<h3>Veranstaltungen nach Teilnehmerzahl';
            if (data.events && data.events.length > 0) {
                html += ' <span style="font-size: 14px; font-weight: normal; color: #666;">(' + data.events.length + ' Veranstaltung' + (data.events.length !== 1 ? 'en' : '') + ')</span>';
            }
            html += '</h3>';
            html += '<p style="color: #666; font-size: 13px; margin-bottom: 12px;"><em>ℹ️ Veranstaltungen werden nach VNR gruppiert (falls vorhanden), nach Titel + Datum (Präsenz) oder nur nach Titel (Online). Mehrere Teilnehmer derselben Veranstaltung werden zusammengefasst. Bei Online-Veranstaltungen mit mehreren Teilnahmedaten wird ein Zeitraum angezeigt.</em></p>';
            if (data.events && data.events.length > 0) {
                html += '<table id="fobi-events-table" class="fobi-stats-table display" style="width:100%">';
                html += '<thead><tr><th>Veranstaltung</th><th>Ort</th><th>Art</th><th>Teilnehmer</th><th>Datum</th><th>VNR</th></tr></thead>';
                html += '<tbody>';
                data.events.forEach(function(item) {
                    html += '<tr>';
                    html += '<td>' + item.title + '</td>';
                    html += '<td>' + item.location + '</td>';
                    html += '<td>' + item.type + '</td>';
                    html += '<td>' + item.participants + '</td>';
                    html += '<td>' + item.date + '</td>';
                    html += '<td>' + (item.vnr || '-') + '</td>';
                    html += '</tr>';
                });
                html += '</tbody></table>';
            } else {
                html += '<div class="fobi-stats-empty">Keine Daten verfügbar</div>';
            }
            html += '</div>';

            // Fortbildungsarten
            html += '<div class="fobi-stats-section">';
            html += '<h3>Fortbildungen nach Art</h3>';
            if (data.by_type && data.by_type.length > 0) {
                if (showCharts) {
                    html += '<div class="fobi-stats-chart-container"><canvas id="fobi-chart-by-type" class="fobi-stats-chart"></canvas></div>';
                }
                html += '<table class="fobi-stats-table">';
                html += '<thead><tr><th>Art</th><th>Anzahl</th><th>Anteil</th></tr></thead>';
                html += '<tbody>';
                data.by_type.forEach(function(item) {
                    html += '<tr>';
                    html += '<td>' + item.type + '</td>';
                    html += '<td>' + item.count + '</td>';
                    html += '<td><span class="fobi-stats-badge fobi-stats-badge-warning">' + item.percentage + '%</span></td>';
                    html += '</tr>';
                });
                html += '</tbody></table>';
            } else {
                html += '<div class="fobi-stats-empty">Keine Daten verfügbar</div>';
            }
            html += '</div>';

            // Bescheinigungen
            html += '<div class="fobi-stats-section">';
            html += '<h3>Fortbildungsbescheinigungen</h3>';
            if (data.certificates_per_year && data.certificates_per_year.length > 0) {
                html += '<table class="fobi-stats-table">';
                html += '<thead><tr><th>Jahr</th><th>Anzahl Bescheinigungen</th><th>Verschiedene Mitglieder</th></tr></thead>';
                html += '<tbody>';
                data.certificates_per_year.forEach(function(item) {
                    html += '<tr>';
                    html += '<td><strong>' + item.year + '</strong></td>';
                    html += '<td>' + item.count + '</td>';
                    html += '<td>' + item.members + '</td>';
                    html += '</tr>';
                });
                html += '</tbody></table>';
            } else {
                html += '<div class="fobi-stats-empty">Keine Bescheinigungen im gewählten Zeitraum</div>';
            }
            html += '</div>';

            $('#fobi-stats-content').html(html);

            // DataTables initialisieren
            if (data.events && data.events.length > 0) {
                // Zerstöre existierende DataTable falls vorhanden
                if ($.fn.DataTable.isDataTable('#fobi-events-table')) {
                    $('#fobi-events-table').DataTable().destroy();
                }
                
                $('#fobi-events-table').DataTable({
                    order: [[3, 'desc']], // Sortiere nach Teilnehmer absteigend
                    pageLength: 25,
                    language: {
                        search: 'Suchen:',
                        lengthMenu: '_MENU_ Einträge pro Seite',
                        info: 'Zeige _START_ bis _END_ von _TOTAL_ Veranstaltungen',
                        infoEmpty: 'Keine Veranstaltungen gefunden',
                        infoFiltered: '(gefiltert aus _MAX_ Einträgen)',
                        paginate: {
                            first: 'Erste',
                            last: 'Letzte',
                            next: 'Nächste',
                            previous: 'Vorherige'
                        },
                        zeroRecords: 'Keine passenden Veranstaltungen gefunden'
                    },
                    dom: 'Blfrtip',
                    buttons: [
                        {
                            extend: 'colvis',
                            text: '🔧 Spalten ein-/ausblenden',
                            columns: ':not(:first-child)',
                            columnText: function(dt, idx, title) {
                                return title;
                            }
                        }
                    ],
                    columnDefs: [
                        { targets: [4, 5], visible: false },
                        { targets: 3, className: 'dt-center' }
                    ]
                });
            }

            // Charts rendern wenn aktiviert
            if (showCharts) {
                console.log('Charts sollen angezeigt werden. Starte Rendering...');
                // Warte länger damit Canvas-Elemente definitiv im DOM sind
                setTimeout(function() {
                    renderCharts(data);
                }, 500);
            } else {
                console.log('Charts sind deaktiviert.');
            }
        }

        function destroyExistingCharts() {
            // Zerstöre alle existierenden Chart-Instanzen
            Object.keys(chartInstances).forEach(function(key) {
                if (chartInstances[key]) {
                    chartInstances[key].destroy();
                    delete chartInstances[key];
                }
            });
        }

        function renderCharts(data) {
            console.log('renderCharts() aufgerufen');
            
            // Alte Charts zerstören
            destroyExistingCharts();

            // Prüfen ob Chart.js verfügbar ist
            if (typeof Chart === 'undefined') {
                console.error('Chart.js ist nicht verfügbar!');
                return;
            }

            console.log('Chart.js Version:', Chart.version);

            // Farbpalette für Diagramme
            var colors = [
                'rgba(102, 126, 234, 0.8)',
                'rgba(118, 75, 162, 0.8)',
                'rgba(237, 100, 166, 0.8)',
                'rgba(255, 154, 158, 0.8)',
                'rgba(250, 208, 196, 0.8)',
                'rgba(156, 204, 101, 0.8)',
                'rgba(77, 182, 172, 0.8)',
                'rgba(255, 193, 7, 0.8)',
                'rgba(255, 87, 34, 0.8)',
                'rgba(96, 125, 139, 0.8)'
            ];

            // Chart: Fortbildungen pro Jahr (Bar Chart)
            if (data.per_year && data.per_year.length > 0) {
                var canvas1 = document.getElementById('fobi-chart-per-year');
                console.log('Canvas fobi-chart-per-year:', canvas1);
                
                if (canvas1) {
                    var ctx1 = canvas1.getContext('2d');
                    console.log('Context für Chart 1:', ctx1);
                    
                    try {
                        chartInstances['perYear'] = new Chart(ctx1, {
                            type: 'bar',
                            data: {
                                labels: data.per_year.map(function(item) { return item.year; }),
                                datasets: [{
                                    label: 'Anzahl Fortbildungen',
                                    data: data.per_year.map(function(item) { return item.count; }),
                                    backgroundColor: 'rgba(102, 126, 234, 0.8)',
                                    borderColor: 'rgba(102, 126, 234, 1)',
                                    borderWidth: 2
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        ticks: {
                                            stepSize: 1
                                        }
                                    }
                                },
                                plugins: {
                                    legend: {
                                        display: false
                                    },
                                    tooltip: {
                                        callbacks: {
                                            label: function(context) {
                                                return 'Fortbildungen: ' + context.parsed.y;
                                            }
                                        }
                                    }
                                }
                            }
                        });
                        console.log('Chart 1 (Fortbildungen pro Jahr) erfolgreich erstellt!');
                    } catch(e) {
                        console.error('Fehler beim Erstellen von Chart 1:', e);
                    }
                } else {
                    console.error('Canvas-Element fobi-chart-per-year nicht gefunden');
                }
            }

            // Chart: Nach Art (Doughnut)
            if (data.by_type && data.by_type.length > 0) {
                var canvas2 = document.getElementById('fobi-chart-by-type');
                console.log('Canvas fobi-chart-by-type:', canvas2);
                
                if (canvas2) {
                    var ctx2 = canvas2.getContext('2d');
                    
                    try {
                        chartInstances['byType'] = new Chart(ctx2, {
                            type: 'doughnut',
                            data: {
                                labels: data.by_type.map(function(item) { return item.type; }),
                                datasets: [{
                                    data: data.by_type.map(function(item) { return item.count; }),
                                    backgroundColor: colors.slice(0, data.by_type.length),
                                    borderWidth: 2,
                                    borderColor: '#fff'
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        position: 'right',
                                        labels: {
                                            padding: 15,
                                            font: { size: 13 }
                                        }
                                    },
                                    tooltip: {
                                        callbacks: {
                                            label: function(context) {
                                                var label = context.label || '';
                                                if (label) {
                                                    label += ': ';
                                                }
                                                label += context.parsed + ' Einträge';
                                                var total = context.dataset.data.reduce(function(a, b) { return a + b; }, 0);
                                                var percentage = ((context.parsed / total) * 100).toFixed(1);
                                                label += ' (' + percentage + '%)';
                                                return label;
                                            }
                                        }
                                    }
                                }
                            }
                        });
                        console.log('Chart 2 erfolgreich erstellt!');
                    } catch(e) {
                        console.error('Fehler beim Erstellen von Chart 2:', e);
                    }
                } else {
                    console.error('Canvas-Element fobi-chart-by-type nicht gefunden');
                }
            }
        }

        // Event-Listener
        $('#fobi-stats-reload').on('click', function() {
            loadStats();
        });

        // Chart-Toggle Event-Listener
        $('#fobi-stats-show-charts').on('change', function() {
            var isChecked = $(this).is(':checked');
            localStorage.setItem('fobi_show_charts', isChecked);
            loadStats();
        });

        // Initial laden - warte bis Chart.js verfügbar ist
        waitForChart(function() {
            $(document).ready(function() {
                loadStats();
            });
        });

    })(jQuery);
    </script>
    <?php
    return ob_get_clean();
}

/**
 * AJAX-Handler für Statistik-Daten
 */
add_action( 'wp_ajax_dgptm_get_fortbildung_stats', 'dgptm_get_fortbildung_stats_ajax' );
add_action( 'wp_ajax_nopriv_dgptm_get_fortbildung_stats', 'dgptm_get_fortbildung_stats_ajax' );

function dgptm_get_fortbildung_stats_ajax() {
    // Nur prüfen ob WordPress läuft - keine Berechtigungsprüfung mehr
    
    $year_from = isset( $_POST['year_from'] ) ? intval( $_POST['year_from'] ) : date('Y') - 4;
    $year_to = isset( $_POST['year_to'] ) ? intval( $_POST['year_to'] ) : date('Y');
    $only_approved = isset( $_POST['only_approved'] ) && $_POST['only_approved'] === '1';

    // Daten sammeln
    $stats = dgptm_calculate_stats( $year_from, $year_to, $only_approved );

    wp_send_json_success( $stats );
}

/**
 * Statistiken berechnen
 */
function dgptm_calculate_stats( $year_from, $year_to, $only_approved = true ) {
    $args = array(
        'post_type'      => 'fortbildung',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => array(
            array(
                'key'     => 'date',
                'value'   => array( $year_from . '-01-01', $year_to . '-12-31' ),
                'compare' => 'BETWEEN',
                'type'    => 'DATE'
            )
        )
    );

    if ( $only_approved ) {
        $args['meta_query'][] = array(
            'key'     => 'freigegeben',
            'value'   => '1',
            'compare' => '='
        );
    }

    $query = new WP_Query( $args );
    $fortbildungen_ids = $query->posts;

    // Basis-Statistiken
    $total_fortbildungen = count( $fortbildungen_ids );
    $unique_members = array();
    $total_points = 0;
    $per_year = array();
    $by_type = array();
    $events = array();
    $member_counts = array();

    foreach ( $fortbildungen_ids as $pid ) {
        $user_id = intval( get_post_meta( $pid, 'user', true ) );
        $date = get_post_meta( $pid, 'date', true );
        $points = floatval( get_post_meta( $pid, 'points', true ) );
        $type = get_post_meta( $pid, 'type', true ) ?: 'Unbekannt';
        $vnr = get_post_meta( $pid, 'vnr', true );
        $title = get_the_title( $pid );
        $location = get_post_meta( $pid, 'location', true );

        // Jahr extrahieren
        $year = date( 'Y', strtotime( $date ) );

        // Unique Members
        if ( $user_id > 0 ) {
            $unique_members[$user_id] = true;
            
            // Member Counts
            if ( ! isset( $member_counts[$user_id] ) ) {
                $member_counts[$user_id] = array( 'count' => 0, 'points' => 0 );
            }
            $member_counts[$user_id]['count']++;
            $member_counts[$user_id]['points'] += $points;
        }

        // Punkte
        $total_points += $points;

        // Pro Jahr
        if ( ! isset( $per_year[$year] ) ) {
            $per_year[$year] = array( 'count' => 0, 'points' => 0, 'members' => array() );
        }
        $per_year[$year]['count']++;
        $per_year[$year]['points'] += $points;
        if ( $user_id > 0 ) {
            $per_year[$year]['members'][$user_id] = true;
        }

        // Nach Typ
        $type = $type ?: 'Unbekannt';
        if ( ! isset( $by_type[$type] ) ) {
            $by_type[$type] = 0;
        }
        $by_type[$type]++;

        // Events gruppieren
        if ( $vnr ) {
            $event_key = 'vnr_' . $vnr;
        } else {
            $normalized_title = strtolower( trim( preg_replace( '/\s+/', ' ', $title ) ) );
            $type_normalized = strtolower( trim( $type ) );
            if ( $type_normalized === 'quiz' ) {
                $event_key = 'title_' . md5( $normalized_title );
            } else {
                $event_key = 'title_' . md5( $normalized_title . '|' . $date );
            }
        }
        
        if ( ! isset( $events[$event_key] ) ) {
            $events[$event_key] = array(
                'title'        => $title,
                'date'         => $date,
                'dates'        => array( $date ),
                'location'     => $location,
                'locations'    => array(),
                'vnr'          => $vnr,
                'type'         => $type,
                'types'        => array(),
                'participants' => array()
            );
        } else {
            if ( strlen( $title ) > strlen( $events[$event_key]['title'] ) ) {
                $events[$event_key]['title'] = $title;
            }
            if ( ! in_array( $date, $events[$event_key]['dates'] ) ) {
                $events[$event_key]['dates'][] = $date;
            }
        }
        
        if ( ! empty( $location ) ) {
            $loc_normalized = trim( $location );
            if ( ! isset( $events[$event_key]['locations'][$loc_normalized] ) ) {
                $events[$event_key]['locations'][$loc_normalized] = 0;
            }
            $events[$event_key]['locations'][$loc_normalized]++;
        }
        
        if ( ! empty( $type ) ) {
            $type_normalized = trim( $type );
            if ( ! isset( $events[$event_key]['types'][$type_normalized] ) ) {
                $events[$event_key]['types'][$type_normalized] = 0;
            }
            $events[$event_key]['types'][$type_normalized]++;
        }
        
        if ( $user_id > 0 ) {
            $events[$event_key]['participants'][$user_id] = true;
        }
    }

    // Durchschnitt pro teilnehmendem Mitglied
    $unique_member_count = count( $unique_members );
    $avg_per_active_member = $unique_member_count > 0 
        ? number_format( $total_fortbildungen / $unique_member_count, 1, ',', '.' )
        : '0';

    // Gesamtzahl aller WordPress-Benutzer abrufen
    $total_members = count_users();
    $total_members_count = isset( $total_members['total_users'] ) ? intval( $total_members['total_users'] ) : 0;
    
    // Durchschnitt pro Mitglied (gesamt)
    $avg_per_all_members = $total_members_count > 0 
        ? number_format( $total_fortbildungen / $total_members_count, 1, ',', '.' )
        : '0';

    // Pro Jahr formatieren
    $per_year_formatted = array();
    foreach ( $per_year as $year => $data ) {
        $per_year_formatted[] = array(
            'year'    => $year,
            'count'   => $data['count'],
            'members' => count( $data['members'] ),
            'points'  => number_format( $data['points'], 1, ',', '.' )
        );
    }
    usort( $per_year_formatted, function( $a, $b ) {
        return $a['year'] - $b['year'];
    });

    // Nach Typ formatieren
    $by_type_formatted = array();
    $total_for_percentage = $total_fortbildungen > 0 ? $total_fortbildungen : 1;
    foreach ( $by_type as $type => $count ) {
        $by_type_formatted[] = array(
            'type'       => $type,
            'count'      => $count,
            'percentage' => number_format( ( $count / $total_for_percentage ) * 100, 1, ',', '.' )
        );
    }
    usort( $by_type_formatted, function( $a, $b ) {
        return $b['count'] - $a['count'];
    });

    // Events formatieren
    $events_formatted = array();
    foreach ( $events as $event_data ) {
        $location = '-';
        if ( ! empty( $event_data['locations'] ) ) {
            arsort( $event_data['locations'] );
            $location = array_key_first( $event_data['locations'] );
        }
        
        $event_type = '-';
        if ( ! empty( $event_data['types'] ) ) {
            arsort( $event_data['types'] );
            $event_type = array_key_first( $event_data['types'] );
        }
        
        if ( strtolower( trim( $event_type ) ) === 'online' && $location === '-' ) {
            $location = 'Online';
        }
        
        $date_display = dgptm_format_date_german( $event_data['date'] );
        if ( ! empty( $event_data['dates'] ) && count( $event_data['dates'] ) > 1 ) {
            $dates_sorted = $event_data['dates'];
            sort( $dates_sorted );
            $first_date = dgptm_format_date_german( $dates_sorted[0] );
            $last_date = dgptm_format_date_german( $dates_sorted[ count($dates_sorted) - 1 ] );
            
            if ( strtolower( trim( $event_type ) ) === 'online' ) {
                $date_display = $first_date . ' - ' . $last_date;
            } else {
                $date_display = dgptm_format_date_german( $event_data['date'] );
            }
        }
        
        $events_formatted[] = array(
            'title'        => $event_data['title'],
            'date'         => $date_display,
            'location'     => $location,
            'type'         => $event_type,
            'vnr'          => $event_data['vnr'] ?: '',
            'participants' => count( $event_data['participants'] )
        );
    }
    usort( $events_formatted, function( $a, $b ) {
        return $b['participants'] - $a['participants'];
    });

    // Top Members
    $top_members = array();
    foreach ( $member_counts as $user_id => $data ) {
        $user = get_userdata( $user_id );
        if ( $user ) {
            $name = trim( ( $user->first_name ?: '' ) . ' ' . ( $user->last_name ?: '' ) );
            if ( $name === '' ) {
                $name = $user->display_name ?: $user->user_login;
            }
            $top_members[] = array(
                'name'   => $name,
                'count'  => $data['count'],
                'points' => number_format( $data['points'], 1, ',', '.' )
            );
        }
    }
    usort( $top_members, function( $a, $b ) {
        return $b['count'] - $a['count'];
    });
    $top_members = array_slice( $top_members, 0, 10 );

    // Bescheinigungen abfragen
    $certificates_query = new WP_Query( array(
        'post_type'      => 'fobi_certificate',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'date_query'     => array(
            array(
                'after'     => $year_from . '-01-01',
                'before'    => $year_to . '-12-31',
                'inclusive' => true
            )
        )
    ) );

    $total_certificates = $certificates_query->found_posts;
    $certificates_per_year = array();
    
    foreach ( $certificates_query->posts as $cert_id ) {
        $created_at = get_post_meta( $cert_id, 'created_at', true );
        $cert_user_id = intval( get_post_meta( $cert_id, 'user_id', true ) );
        
        if ( $created_at ) {
            $cert_year = date( 'Y', strtotime( $created_at ) );
            
            if ( ! isset( $certificates_per_year[$cert_year] ) ) {
                $certificates_per_year[$cert_year] = array(
                    'count'   => 0,
                    'members' => array()
                );
            }
            
            $certificates_per_year[$cert_year]['count']++;
            
            if ( $cert_user_id > 0 ) {
                $certificates_per_year[$cert_year]['members'][$cert_user_id] = true;
            }
        }
    }

    // Bescheinigungen pro Jahr formatieren
    $certificates_per_year_formatted = array();
    foreach ( $certificates_per_year as $year => $data ) {
        $certificates_per_year_formatted[] = array(
            'year'    => $year,
            'count'   => $data['count'],
            'members' => count( $data['members'] )
        );
    }
    usort( $certificates_per_year_formatted, function( $a, $b ) {
        return $a['year'] - $b['year'];
    });

    return array(
        'total_fortbildungen'     => $total_fortbildungen,
        'unique_members'          => $unique_member_count,
        'total_members'           => $total_members_count,
        'avg_per_active_member'   => $avg_per_active_member,
        'avg_per_all_members'     => $avg_per_all_members,
        'total_points'            => number_format( $total_points, 1, ',', '.' ),
        'total_certificates'      => $total_certificates,
        'certificates_per_year'   => $certificates_per_year_formatted,
        'per_year'                => $per_year_formatted,
        'by_type'                 => $by_type_formatted,
        'events'                  => $events_formatted,
        'top_members'             => $top_members
    );
}

/**
 * Datum formatieren (d.m.Y)
 */
function dgptm_format_date_german( $date_string ) {
    if ( ! $date_string ) {
        return '-';
    }
    
    $timestamp = strtotime( $date_string );
    if ( ! $timestamp ) {
        return $date_string;
    }
    
    return date_i18n( 'd.m.Y', $timestamp );
}