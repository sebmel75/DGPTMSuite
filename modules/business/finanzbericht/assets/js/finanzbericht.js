(function($) {
    'use strict';

    var state = {
        report: null,
        year: null,
    };

    function init() {
        // Tab-Klick
        $('.dgptm-fb-tab').on('click', function() {
            var report = $(this).data('report');
            $('.dgptm-fb-tab').removeClass('active');
            $(this).addClass('active');
            state.report = report;
            updateYearSelect();
            loadReport();
        });

        // Jahr-Wechsel
        $('#dgptm-fb-year').on('change', function() {
            state.year = parseInt($(this).val());
            loadReport();
        });

        // Reload
        $('#dgptm-fb-reload').on('click', loadReport);

        // Ersten Tab aktivieren
        $('.dgptm-fb-tab:first').trigger('click');
    }

    function updateYearSelect() {
        // Erstmal Default-Jahre, werden nach erstem Load aktualisiert
        var sel = $('#dgptm-fb-year');
        var current = new Date().getFullYear();
        sel.empty();
        for (var y = current; y >= 2023; y--) {
            sel.append($('<option>').val(y).text(y));
        }
        state.year = current;
    }

    function loadReport() {
        if (!state.report) return;

        var $content = $('#dgptm-fb-content');
        var $loading = $('#dgptm-fb-loading');
        var $source = $('#dgptm-fb-source');

        $content.hide();
        $loading.show();
        $source.text('');

        $.post(dgptmFB.ajaxUrl, {
            action: 'dgptm_fb_get_report',
            nonce: dgptmFB.nonce,
            report: state.report,
            year: state.year
        }, function(response) {
            $loading.hide();
            $content.show();

            if (!response.success) {
                $content.html('<p class="dgptm-fb-error">' + (response.data?.message || 'Fehler') + '</p>');
                return;
            }

            var data = response.data;

            // Jahre-Dropdown aktualisieren
            if (data.years && data.years.length) {
                var sel = $('#dgptm-fb-year');
                var current = state.year;
                sel.empty();
                data.years.sort(function(a, b) { return b - a; });
                data.years.forEach(function(y) {
                    sel.append($('<option>').val(y).text(y).prop('selected', y === current));
                });
            }

            // Quelle anzeigen
            if (data.source === 'static') {
                $source.text('Historische Daten').removeClass('live').addClass('static');
            } else if (data.source === 'live') {
                $source.text('Live (Zoho Books)').removeClass('static').addClass('live');
            }

            if (data.error) {
                $content.html('<p class="dgptm-fb-error">' + data.error + '</p>');
                return;
            }

            renderReport(data);
        });
    }

    function feur(amount) {
        if (amount == null) return '0,00 EUR';
        return amount.toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' EUR';
    }

    function renderReport(data) {
        var html = '';

        // Titel
        html += '<div class="dgptm-fb-report-header">';
        html += '<h3>' + esc(data.title || '') + '</h3>';
        if (data.period) html += '<p class="dgptm-fb-period">' + esc(data.period) + '</p>';
        if (data.location) html += '<p class="dgptm-fb-location">' + esc(data.location) + '</p>';
        html += '</div>';

        // KPIs
        html += '<div class="dgptm-fb-kpis">';
        html += kpiBox('Einnahmen', data.income?.total || 0, 'green');
        html += kpiBox('Ausgaben', data.expenses?.total || 0, 'red');
        var net = data.net_result || 0;
        html += kpiBox('Ergebnis', net, net >= 0 ? 'green' : 'red');
        html += '</div>';

        // Einnahmen-Tabelle
        if (data.income?.categories) {
            html += '<div class="dgptm-fb-section">';
            html += '<h4>Einnahmen nach Kategorie</h4>';
            html += categoryTable(data.income.categories, data.income.total);
            html += '</div>';
        }

        // Ausgaben-Tabelle
        if (data.expenses?.categories) {
            html += '<div class="dgptm-fb-section">';
            html += '<h4>Ausgaben nach Kategorie</h4>';
            html += categoryTable(data.expenses.categories, data.expenses.total);
            html += '</div>';
        }

        // Hinweis
        if (data.expenses?.note) {
            html += '<p class="dgptm-fb-note">' + esc(data.expenses.note) + '</p>';
        }

        // Einzelpositionen (Live-Daten)
        if (data.expenses?.items && data.expenses.items.length) {
            html += '<div class="dgptm-fb-section">';
            html += '<h4>Ausgaben-Positionen</h4>';
            html += itemsTable(data.expenses.items);
            html += '</div>';
        }

        $('#dgptm-fb-content').html(html);
    }

    function kpiBox(label, value, color) {
        return '<div class="dgptm-fb-kpi dgptm-fb-kpi-' + color + '">' +
               '<span class="dgptm-fb-kpi-label">' + label + '</span>' +
               '<span class="dgptm-fb-kpi-value">' + feur(value) + '</span></div>';
    }

    function categoryTable(cats, total) {
        var html = '<table class="dgptm-fb-table"><thead><tr><th>Kategorie</th><th>Anzahl</th><th>Betrag</th></tr></thead><tbody>';
        var keys = Object.keys(cats);
        keys.forEach(function(key) {
            var c = cats[key];
            html += '<tr><td>' + esc(key) + '</td><td class="num">' + (c.count || '-') + '</td><td class="num">' + feur(c.total) + '</td></tr>';
        });
        html += '</tbody><tfoot><tr><td><strong>Gesamt</strong></td><td></td><td class="num"><strong>' + feur(total) + '</strong></td></tr></tfoot></table>';
        return html;
    }

    function itemsTable(items) {
        items.sort(function(a, b) { return b.total - a.total; });
        var html = '<table class="dgptm-fb-table"><thead><tr><th>Datum</th><th>Lieferant</th><th>Betrag</th></tr></thead><tbody>';
        items.forEach(function(item) {
            html += '<tr><td>' + esc(item.date || '') + '</td><td>' + esc(item.vendor || item.customer || '') + '</td><td class="num">' + feur(item.total) + '</td></tr>';
        });
        html += '</tbody></table>';
        return html;
    }

    function esc(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    $(init);

})(jQuery);
