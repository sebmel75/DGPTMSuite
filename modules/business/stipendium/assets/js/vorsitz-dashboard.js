/**
 * DGPTM Stipendium — Vorsitzenden-Dashboard
 *
 * Funktionen:
 * - Bewerbungen per AJAX laden und nach Status gruppiert anzeigen
 * - Gutachter einladen (Modal + AJAX)
 * - Status-Aktionen (Freigeben, Ablehnen, Vergeben, Archivieren)
 */
(function($) {
    'use strict';

    var config = window.dgptmVorsitz || {};
    var ajaxUrl = config.ajaxUrl || '';
    var nonce = config.nonce || '';
    var strings = config.strings || {};

    /**
     * Bewerbungen laden.
     */
    function loadBewerbungen() {
        var runde = $('#dgptm-vorsitz-runde').val();
        var typ = $('#dgptm-vorsitz-runde option:selected').data('typ') || '';

        if (!runde) return;

        $('#dgptm-vorsitz-loading').show();
        $('#dgptm-vorsitz-empty').hide();

        // Alle Sektionen verstecken
        $('.dgptm-vorsitz-section').hide();

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: {
                action: 'dgptm_stipendium_load_bewerbungen',
                nonce: nonce,
                runde: runde,
                typ: typ
            },
            success: function(response) {
                $('#dgptm-vorsitz-loading').hide();

                if (!response.success) {
                    alert(response.data || strings.fehler);
                    return;
                }

                renderDashboard(response.data);
            },
            error: function() {
                $('#dgptm-vorsitz-loading').hide();
                alert(strings.fehler || 'Fehler beim Laden.');
            }
        });
    }

    /**
     * Dashboard-Inhalt rendern.
     */
    function renderDashboard(data) {
        var hasEntries = false;

        // Geprueft
        if (data.geprueft && data.geprueft.length > 0) {
            hasEntries = true;
            $('#dgptm-section-geprueft').show();
            $('#dgptm-count-geprueft').text(data.geprueft.length);
            $('#dgptm-cards-geprueft').html(data.geprueft.map(renderGeprueftCard).join(''));
        }

        // Freigegeben
        if (data.freigegeben && data.freigegeben.length > 0) {
            hasEntries = true;
            $('#dgptm-section-freigegeben').show();
            $('#dgptm-count-freigegeben').text(data.freigegeben.length);
            $('#dgptm-cards-freigegeben').html(data.freigegeben.map(renderFreigegebenCard).join(''));
        }

        // In Bewertung
        if (data.in_bewertung && data.in_bewertung.length > 0) {
            hasEntries = true;
            $('#dgptm-section-in_bewertung').show();
            $('#dgptm-count-in_bewertung').text(data.in_bewertung.length);
            $('#dgptm-cards-in_bewertung').html(data.in_bewertung.map(renderInBewertungCard).join(''));
        }

        // Abgeschlossen
        if (data.abgeschlossen && data.abgeschlossen.length > 0) {
            hasEntries = true;
            $('#dgptm-section-abgeschlossen').show();
            $('#dgptm-count-abgeschlossen').text(data.abgeschlossen.length);
            renderRankingTable(data.abgeschlossen);
            $('#dgptm-bulk-actions').show();
        }

        if (!hasEntries) {
            $('#dgptm-vorsitz-empty').show();
        }
    }

    /**
     * Karte: Geprueft (Freigeben/Ablehnen).
     */
    function renderGeprueftCard(item) {
        var datum = item.eingangsdatum ? formatDate(item.eingangsdatum) : '';
        return '<div class="dgptm-vorsitz-card">'
            + '<div class="dgptm-vorsitz-card-header">'
            + '<strong>' + escHtml(item.name) + '</strong>'
            + '<span class="dgptm-vorsitz-card-tag">' + escHtml(item.stipendientyp) + '</span>'
            + '</div>'
            + (datum ? '<div class="dgptm-vorsitz-card-meta">Eingang: ' + datum + '</div>' : '')
            + '<div class="dgptm-vorsitz-card-actions">'
            + '<button class="dgptm-vorsitz-btn dgptm-vorsitz-btn--sm dgptm-vorsitz-btn--primary" '
            + 'data-action="freigeben" data-id="' + item.id + '">Freigeben</button>'
            + '<button class="dgptm-vorsitz-btn dgptm-vorsitz-btn--sm dgptm-vorsitz-btn--danger" '
            + 'data-action="ablehnen" data-id="' + item.id + '">Ablehnen</button>'
            + '</div></div>';
    }

    /**
     * Karte: Freigegeben (Gutachter einladen).
     */
    function renderFreigegebenCard(item) {
        var gutachterHtml = '';
        if (item.gutachter && item.gutachter.length > 0) {
            gutachterHtml = '<div class="dgptm-vorsitz-gutachter-list">';
            item.gutachter.forEach(function(g) {
                var icon = g.status === 'abgeschlossen' ? '&#10003;' : '&#9675;';
                var cls = g.status === 'abgeschlossen' ? 'done' : 'pending';
                gutachterHtml += '<div class="dgptm-vorsitz-gutachter-item dgptm-vorsitz-gutachter-item--' + cls + '">'
                    + '<span>' + icon + '</span> ' + escHtml(g.name)
                    + ' <span class="dgptm-vorsitz-gutachter-status">' + escHtml(g.status) + '</span>'
                    + '</div>';
            });
            gutachterHtml += '</div>';
        }

        return '<div class="dgptm-vorsitz-card">'
            + '<div class="dgptm-vorsitz-card-header">'
            + '<strong>' + escHtml(item.name) + '</strong>'
            + '<span class="dgptm-vorsitz-card-tag">' + escHtml(item.stipendientyp) + '</span>'
            + '</div>'
            + '<div class="dgptm-vorsitz-card-meta">Gutachter: ' + item.gutachter_done + '/' + item.gutachter_total + ' zugewiesen</div>'
            + gutachterHtml
            + '<div class="dgptm-vorsitz-card-actions">'
            + '<button class="dgptm-vorsitz-btn dgptm-vorsitz-btn--sm dgptm-vorsitz-btn--primary" '
            + 'data-action="einladen" data-id="' + item.id + '" data-name="' + escAttr(item.name) + '">+ Gutachter einladen</button>'
            + '</div></div>';
    }

    /**
     * Karte: In Bewertung.
     */
    function renderInBewertungCard(item) {
        var gutachterHtml = '';
        if (item.gutachter && item.gutachter.length > 0) {
            gutachterHtml = '<div class="dgptm-vorsitz-gutachter-list">';
            item.gutachter.forEach(function(g) {
                var icon = g.status === 'abgeschlossen' ? '&#10003;' : '&#9675;';
                var cls = g.status === 'abgeschlossen' ? 'done' : 'pending';
                gutachterHtml += '<div class="dgptm-vorsitz-gutachter-item dgptm-vorsitz-gutachter-item--' + cls + '">'
                    + '<span>' + icon + '</span> ' + escHtml(g.name)
                    + '</div>';
            });
            gutachterHtml += '</div>';
        }

        return '<div class="dgptm-vorsitz-card">'
            + '<div class="dgptm-vorsitz-card-header">'
            + '<strong>' + escHtml(item.name) + '</strong>'
            + '<span class="dgptm-vorsitz-card-tag">' + escHtml(item.stipendientyp) + '</span>'
            + '</div>'
            + '<div class="dgptm-vorsitz-card-meta">' + item.gutachter_done + '/' + item.gutachter_total + ' Gutachten abgeschlossen</div>'
            + gutachterHtml
            + '<div class="dgptm-vorsitz-card-actions">'
            + '<button class="dgptm-vorsitz-btn dgptm-vorsitz-btn--sm dgptm-vorsitz-btn--primary" '
            + 'data-action="einladen" data-id="' + item.id + '" data-name="' + escAttr(item.name) + '">+ Weiteren Gutachter einladen</button>'
            + '</div></div>';
    }

    /**
     * Ranking-Tabelle rendern.
     */
    function renderRankingTable(items) {
        // Nach Score sortieren (absteigend)
        items.sort(function(a, b) {
            return (b.gesamtscore || 0) - (a.gesamtscore || 0);
        });

        var html = '<table class="dgptm-vorsitz-ranking-table">'
            + '<thead><tr>'
            + '<th>Rang</th><th>Name</th><th>Score</th><th>Gutachten</th><th>Aktion</th>'
            + '</tr></thead><tbody>';

        items.forEach(function(item, idx) {
            var rang = item.foerderfaehig ? (item.rang || (idx + 1)) : '&mdash;';
            var scoreStr = item.gesamtscore !== null ? parseFloat(item.gesamtscore).toFixed(2) : '--';
            var foerderClass = item.foerderfaehig ? '' : ' dgptm-vorsitz-nicht-foerderfaehig';
            var vergebenBadge = item.vergeben ? ' <span class="dgptm-vorsitz-vergeben-badge">vergeben</span>' : '';

            html += '<tr class="' + foerderClass + '">'
                + '<td>' + rang + '</td>'
                + '<td>' + escHtml(item.name) + vergebenBadge
                + (item.foerderfaehig ? '' : ' <span class="dgptm-vorsitz-hint">nicht foerderfaehig</span>') + '</td>'
                + '<td>' + scoreStr + '</td>'
                + '<td>' + item.gutachter_done + '/' + item.gutachter_total + '</td>'
                + '<td>';

            if (!item.vergeben && item.foerderfaehig) {
                html += '<button class="dgptm-vorsitz-btn dgptm-vorsitz-btn--xs dgptm-vorsitz-btn--primary" '
                    + 'data-action="vergeben" data-id="' + item.id + '">Vergeben</button>';
            }

            html += '</td></tr>';
        });

        html += '</tbody></table>';
        $('#dgptm-ranking-table').html(html);
    }

    /**
     * Aktion ausfuehren (Freigeben, Ablehnen, Vergeben).
     */
    function executeAction(action, stipendiumId, extraData) {
        var confirmMsg = strings['confirm_' + action] || ('Aktion "' + action + '" ausfuehren?');

        if (!confirm(confirmMsg)) return;

        var postData = {
            action: 'dgptm_stipendium_' + action,
            nonce: nonce,
            stipendium_id: stipendiumId,
            runde: $('#dgptm-vorsitz-runde').val()
        };

        if (extraData) {
            $.extend(postData, extraData);
        }

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: postData,
            success: function(response) {
                if (response.success) {
                    loadBewerbungen(); // Dashboard neu laden
                } else {
                    alert(response.data?.message || response.data || strings.fehler);
                }
            },
            error: function() {
                alert(strings.fehler || 'Fehler bei der Aktion.');
            }
        });
    }

    /**
     * Einladungs-Modal oeffnen.
     */
    function openEinladungModal(stipendiumId, bewerberName) {
        $('#dgptm-einladung-stipendium-id').val(stipendiumId);
        $('#dgptm-einladung-bewerber-info').text('Bewerbung: ' + bewerberName);
        $('#dgptm-einladung-name').val('');
        $('#dgptm-einladung-email').val('');
        $('#dgptm-einladung-modal').fadeIn(200);
    }

    /**
     * Einladung senden.
     */
    function sendEinladung() {
        var stipendiumId = $('#dgptm-einladung-stipendium-id').val();
        var name = $('#dgptm-einladung-name').val().trim();
        var email = $('#dgptm-einladung-email').val().trim();
        var frist = $('#dgptm-einladung-frist').val();

        if (!name || !email) {
            alert('Bitte Name und E-Mail ausfuellen.');
            return;
        }

        var sendBtn = $('#dgptm-einladung-send');
        sendBtn.text('Wird gesendet...').prop('disabled', true);

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: {
                action: 'dgptm_stipendium_einladen',
                nonce: nonce,
                stipendium_id: stipendiumId,
                gutachter_name: name,
                gutachter_email: email,
                frist: frist,
                runde: $('#dgptm-vorsitz-runde').val()
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message || strings.einladung_gesendet);
                    $('#dgptm-einladung-modal').fadeOut(200);
                    loadBewerbungen();
                } else {
                    alert(response.data?.message || response.data || strings.fehler);
                }
            },
            error: function() {
                alert(strings.fehler || 'Fehler beim Senden.');
            },
            complete: function() {
                sendBtn.text('Einladung senden').prop('disabled', false);
            }
        });
    }

    /**
     * Hilfsfunktionen.
     */
    function escHtml(str) {
        if (!str) return '';
        return $('<span>').text(str).html();
    }

    function escAttr(str) {
        if (!str) return '';
        return str.replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;');
    }

    function formatDate(dateStr) {
        if (!dateStr) return '';
        var parts = dateStr.split('-');
        if (parts.length === 3) {
            return parts[2] + '.' + parts[1] + '.' + parts[0];
        }
        return dateStr;
    }

    /**
     * Initialisierung.
     */
    $(document).ready(function() {
        // Initial laden
        loadBewerbungen();

        // Runde wechseln
        $('#dgptm-vorsitz-runde').on('change', loadBewerbungen);

        // Button-Aktionen (Event Delegation)
        $(document).on('click', '[data-action]', function(e) {
            e.preventDefault();
            var btn = $(this);
            var action = btn.data('action');
            var id = btn.data('id');
            var name = btn.data('name');

            switch (action) {
                case 'freigeben':
                case 'ablehnen':
                case 'vergeben':
                    executeAction(action, id);
                    break;
                case 'einladen':
                    openEinladungModal(id, name || '');
                    break;
                case 'archivieren':
                    executeAction('archivieren', '', {
                        typ: $('#dgptm-vorsitz-runde option:selected').data('typ') || ''
                    });
                    break;
                case 'pdf':
                    executeAction('pdf', '');
                    break;
            }
        });

        // Einladungs-Modal
        $('#dgptm-einladung-send').on('click', sendEinladung);
        $('#dgptm-einladung-close, #dgptm-einladung-cancel').on('click', function() {
            $('#dgptm-einladung-modal').fadeOut(200);
        });

        // Modal schliessen bei Klick ausserhalb
        $('#dgptm-einladung-modal').on('click', function(e) {
            if ($(e.target).hasClass('dgptm-vorsitz-modal-overlay')) {
                $(this).fadeOut(200);
            }
        });
    });

})(jQuery);
