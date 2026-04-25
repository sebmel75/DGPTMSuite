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
     * Helfer: gemeinsamer Karten-Header inkl. Manuell-Tag.
     */
    function cardHeader(item) {
        var manualTag = item.is_manual
            ? ' <span class="dgptm-vorsitz-card-tag dgptm-vorsitz-card-tag--manual">manuell</span>'
            : '';
        return '<div class="dgptm-vorsitz-card-header">'
            + '<strong>' + escHtml(item.name) + '</strong>'
            + '<span class="dgptm-vorsitz-card-tag">' + escHtml(item.stipendientyp) + '</span>'
            + manualTag
            + '</div>'
            + (item.projekt_titel ? '<div class="dgptm-vorsitz-card-projekt">' + escHtml(item.projekt_titel) + '</div>' : '')
            + (item.bemerkung ? '<div class="dgptm-vorsitz-card-bemerkung">' + escHtml(item.bemerkung) + '</div>' : '');
    }

    /**
     * Helfer: Edit/Delete-Buttons fuer manuelle Bewerbungen.
     */
    function manualButtons(item) {
        if (!item.is_manual) return '';
        return '<button class="dgptm-fe-btn dgptm-fe-btn-small" '
            + 'data-action="manuell-edit" data-id="' + item.id + '">Bearbeiten</button>'
            + '<button class="dgptm-fe-btn dgptm-fe-btn-small dgptm-fe-btn-danger" '
            + 'data-action="manuell-delete" data-id="' + item.id + '">Löschen</button>';
    }

    /**
     * Karte: Geprueft (Freigeben/Ablehnen).
     */
    function renderGeprueftCard(item) {
        var datum = item.eingangsdatum ? formatDate(item.eingangsdatum) : '';
        return '<div class="dgptm-vorsitz-card">'
            + cardHeader(item)
            + (datum ? '<div class="dgptm-vorsitz-card-meta">Eingang: ' + datum + '</div>' : '')
            + '<div class="dgptm-vorsitz-card-actions">'
            + '<button class="dgptm-fe-btn dgptm-fe-btn-small dgptm-fe-btn-primary" '
            + 'data-action="freigeben" data-id="' + item.id + '">Freigeben</button>'
            + '<button class="dgptm-fe-btn dgptm-fe-btn-small dgptm-fe-btn-danger" '
            + 'data-action="ablehnen" data-id="' + item.id + '">Ablehnen</button>'
            + manualButtons(item)
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
            + cardHeader(item)
            + '<div class="dgptm-vorsitz-card-meta">Gutachter: ' + item.gutachter_done + '/' + item.gutachter_total + ' zugewiesen</div>'
            + gutachterHtml
            + '<div class="dgptm-vorsitz-card-actions">'
            + '<button class="dgptm-fe-btn dgptm-fe-btn-small dgptm-fe-btn-primary" '
            + 'data-action="einladen" data-id="' + item.id + '" data-name="' + escAttr(item.name) + '">+ Gutachter einladen</button>'
            + manualButtons(item)
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
            + cardHeader(item)
            + '<div class="dgptm-vorsitz-card-meta">' + item.gutachter_done + '/' + item.gutachter_total + ' Gutachten abgeschlossen</div>'
            + gutachterHtml
            + '<div class="dgptm-vorsitz-card-actions">'
            + '<button class="dgptm-fe-btn dgptm-fe-btn-small dgptm-fe-btn-primary" '
            + 'data-action="einladen" data-id="' + item.id + '" data-name="' + escAttr(item.name) + '">+ Weiteren Gutachter einladen</button>'
            + manualButtons(item)
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
                html += '<button class="dgptm-fe-btn dgptm-fe-btn-small dgptm-fe-btn-primary" '
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
        var savePool = $('#dgptm-einladung-save-pool').is(':checked') ? 1 : 0;

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
                save_pool: savePool,
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

    /* ──────────────────────────────────────────
     * Laufende Gutachten — Uebersicht
     * ────────────────────────────────────────── */

    function loadLaufende() {
        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: { action: 'dgptm_stipendium_laufende', nonce: nonce },
            success: function(response) {
                if (!response.success) return;
                renderLaufende(response.data.items || []);
            }
        });
    }

    function renderLaufende(items) {
        var $sec = $('#dgptm-section-laufende');
        var $box = $('#dgptm-laufende-table');
        if (!items.length) { $sec.hide(); return; }
        $('#dgptm-count-laufende').text(items.length);

        var html = '<table><thead><tr>'
            + '<th>Bewerber:in</th><th>Gutachter:in</th><th>Status</th>'
            + '<th>Frist</th><th>Tage offen</th><th>Aktion</th>'
            + '</tr></thead><tbody>';

        items.forEach(function(it) {
            var rowCls = it.ueberfaellig ? 'dgptm-vorsitz-laufende-row--ueberfaellig' : '';
            var statusCls = 'dgptm-vorsitz-laufende-status--' + it.status;
            var statusLabel = it.status === 'entwurf' ? 'Entwurf' : 'ausstehend';
            var fristTxt = it.frist;
            if (it.ueberfaellig) fristTxt += ' (überfällig)';

            html += '<tr class="' + rowCls + '">'
                + '<td>' + escHtml(it.bewerber_name) + '</td>'
                + '<td>' + escHtml(it.gutachter_name) + '<br><small style="color:#888;">' + escHtml(it.gutachter_email) + '</small></td>'
                + '<td><span class="dgptm-vorsitz-laufende-status ' + statusCls + '">' + statusLabel + '</span></td>'
                + '<td class="dgptm-vorsitz-frist">' + fristTxt + '</td>'
                + '<td>' + it.tage_offen + ' Tage</td>'
                + '<td><button class="dgptm-fe-btn dgptm-fe-btn-small" data-action="erinnern" data-token-id="' + it.id + '">Erinnern</button></td>'
                + '</tr>';
        });

        html += '</tbody></table>';
        $box.html(html);
        $sec.show();
    }

    function sendErinnerung(tokenId) {
        $.ajax({
            url: ajaxUrl, method: 'POST',
            data: { action: 'dgptm_stipendium_erinnern', nonce: nonce, token_id: tokenId },
            success: function(response) {
                alert(response.success
                    ? (response.data?.message || 'Erinnerung gesendet.')
                    : (response.data?.message || response.data || strings.fehler));
            },
            error: function() { alert(strings.fehler); }
        });
    }

    /* ──────────────────────────────────────────
     * Gutachter-Stammdaten — Modal
     * ────────────────────────────────────────── */

    function openGutachterModal() {
        $('#dgptm-gutachter-modal').fadeIn(150);
        loadGutachterList('');
    }

    function closeGutachterModal() { $('#dgptm-gutachter-modal').fadeOut(150); }

    function loadGutachterList(search) {
        var $list = $('#dgptm-gutachter-list');
        $list.html('<p style="text-align:center;color:#888;">Wird geladen...</p>');
        $.ajax({
            url: ajaxUrl, method: 'POST',
            data: { action: 'dgptm_stipendium_pool_list', nonce: nonce, search: search || '' },
            success: function(response) {
                if (!response.success) {
                    $list.html('<p style="color:#d00;">' + (response.data || 'Fehler') + '</p>');
                    return;
                }
                var items = response.data.items || [];
                if (!items.length) {
                    $list.html('<p style="text-align:center;color:#888;padding:20px;">Noch keine Gutachter:innen erfasst.</p>');
                    return;
                }
                var html = '<table class="dgptm-vorsitz-gutachter-table"><thead><tr>'
                    + '<th>Name</th><th>E-Mail</th><th>Fachgebiet</th><th>Mitglied</th><th>Status</th><th></th>'
                    + '</tr></thead><tbody>';
                items.forEach(function(g) {
                    html += '<tr>'
                        + '<td>' + escHtml(g.name) + '</td>'
                        + '<td>' + escHtml(g.email) + '</td>'
                        + '<td>' + escHtml(g.fachgebiet || '—') + '</td>'
                        + '<td>' + (g.mitglied == 1 ? 'Ja' : '—') + '</td>'
                        + '<td>' + (g.aktiv == 1 ? 'aktiv' : 'inaktiv') + '</td>'
                        + '<td>'
                        + '<button class="dgptm-fe-btn dgptm-fe-btn-small" data-action="pool-edit" data-id="' + g.id + '">Bearbeiten</button>'
                        + (g.aktiv == 1
                            ? ' <button class="dgptm-fe-btn dgptm-fe-btn-small dgptm-fe-btn-danger" data-action="pool-delete" data-id="' + g.id + '">Deaktivieren</button>'
                            : '')
                        + '</td></tr>';
                });
                html += '</tbody></table>';
                $list.html(html);
                $list.data('items', items);
            }
        });
    }

    function openGutachterForm(item) {
        $('#dgptm-gutachter-form').show();
        $('#dgptm-gutachter-form-id').val(item ? item.id : '');
        $('#dgptm-gutachter-form-title').text(item ? 'Gutachter:in bearbeiten' : 'Neue:r Gutachter:in');
        $('#dgptm-gutachter-form-name').val(item ? item.name : '');
        $('#dgptm-gutachter-form-email').val(item ? item.email : '');
        $('#dgptm-gutachter-form-fachgebiet').val(item ? (item.fachgebiet || '') : '');
        $('#dgptm-gutachter-form-mitglied').val(item ? String(item.mitglied || 0) : '0');
        $('#dgptm-gutachter-form-notizen').val(item ? (item.notizen || '') : '');
    }

    function closeGutachterForm() {
        $('#dgptm-gutachter-form').hide();
    }

    function saveGutachter() {
        var data = {
            action:     'dgptm_stipendium_pool_save',
            nonce:      nonce,
            id:         $('#dgptm-gutachter-form-id').val() || 0,
            name:       $('#dgptm-gutachter-form-name').val().trim(),
            email:      $('#dgptm-gutachter-form-email').val().trim(),
            fachgebiet: $('#dgptm-gutachter-form-fachgebiet').val().trim(),
            mitglied:   $('#dgptm-gutachter-form-mitglied').val(),
            notizen:    $('#dgptm-gutachter-form-notizen').val(),
            aktiv:      1
        };
        if (!data.name || !data.email) { alert('Name und E-Mail sind Pflicht.'); return; }
        $.ajax({
            url: ajaxUrl, method: 'POST', data: data,
            success: function(response) {
                if (response.success) {
                    closeGutachterForm();
                    loadGutachterList($('#dgptm-gutachter-search').val());
                } else {
                    alert(response.data?.message || response.data || strings.fehler);
                }
            }
        });
    }

    function deleteGutachter(id) {
        if (!confirm('Diesen Gutachter-Eintrag deaktivieren? (Bestehende Tokens bleiben erhalten.)')) return;
        $.ajax({
            url: ajaxUrl, method: 'POST',
            data: { action: 'dgptm_stipendium_pool_delete', nonce: nonce, id: id },
            success: function(response) {
                if (response.success) {
                    loadGutachterList($('#dgptm-gutachter-search').val());
                } else {
                    alert(response.data?.message || response.data || strings.fehler);
                }
            }
        });
    }

    /* ──────────────────────────────────────────
     * Stipendiums-Runde — Modal
     * ────────────────────────────────────────── */

    function populateRundeTypDropdown() {
        var $sel = $('#dgptm-runde-typ-id');
        if (!$sel.length) return;
        $sel.find('option:not(:first)').remove();
        var typen = config.stipendientypen || [];
        typen.forEach(function(t) {
            if (!t.id) return;
            $sel.append('<option value="' + escAttr(t.id) + '" data-bezeichnung="' + escAttr(t.bezeichnung || '') + '" data-runde="' + escAttr(t.runde || '') + '">'
                + escHtml(t.bezeichnung || t.id) + (t.runde ? ' — ' + escHtml(t.runde) : '')
                + '</option>');
        });
    }

    function openRundeModal() {
        $('#dgptm-runde-typ-id').val('');
        $('#dgptm-runde-bezeichnung').val('');
        $('#dgptm-runde-name').val('');
        $('#dgptm-runde-start').val('');
        $('#dgptm-runde-ende').val('');
        $('#dgptm-runde-modal').fadeIn(150);
    }

    function closeRundeModal() {
        $('#dgptm-runde-modal').fadeOut(150);
    }

    function saveRunde() {
        var typId       = $('#dgptm-runde-typ-id').val();
        var bezeichnung = $('#dgptm-runde-bezeichnung').val().trim();
        var runde       = $('#dgptm-runde-name').val().trim();
        var start       = $('#dgptm-runde-start').val();
        var ende        = $('#dgptm-runde-ende').val();

        if (!bezeichnung || !runde) {
            alert('Bitte Bezeichnung und Runden-Name angeben.');
            return;
        }

        var $btn = $('#dgptm-runde-save');
        $btn.prop('disabled', true).text('Speichern...');

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: {
                action: 'dgptm_stipendium_save_runde',
                nonce: nonce,
                typ_id: typId,
                bezeichnung: bezeichnung,
                runde: runde,
                start: start,
                ende: ende
            },
            success: function(response) {
                if (response.success) {
                    closeRundeModal();
                    var neueRunden = response.data.runden || [];
                    config.stipendientypen = neueRunden.map(function(r){
                        return { id: r.id, bezeichnung: r.bezeichnung, runde: r.runde };
                    });
                    populateRundeTypDropdown();
                    populateTypDropdown();
                    refreshRundenFilter(neueRunden, response.data.typ);
                    loadBewerbungen();
                } else {
                    alert(response.data?.message || response.data || strings.fehler);
                }
            },
            error: function() { alert(strings.fehler || 'Fehler beim Speichern.'); },
            complete: function() {
                $btn.prop('disabled', false).text('Runde speichern');
            }
        });
    }

    function refreshRundenFilter(runden, gewaehlt) {
        var $sel = $('#dgptm-vorsitz-runde');
        if (!$sel.length) return;
        var current = $sel.val();
        $sel.empty();
        runden.forEach(function(t) {
            $sel.append('<option value="' + escAttr(t.runde) + '" data-typ="' + escAttr(t.bezeichnung) + '">'
                + escHtml(t.bezeichnung) + ' — ' + escHtml(t.runde) + '</option>');
        });
        if (gewaehlt && gewaehlt.runde) {
            $sel.val(gewaehlt.runde);
        } else if (current) {
            $sel.val(current);
        }
    }

    /* ──────────────────────────────────────────
     * Manuelle Bewerbung — Modal
     * ────────────────────────────────────────── */

    var $manuellModal;

    function populateTypDropdown() {
        var $sel = $('#dgptm-manuell-typ');
        if (!$sel.length) return;
        $sel.empty();
        var typen = config.stipendientypen || [];
        if (!typen.length) {
            $sel.append('<option value="Promotionsstipendium">Promotionsstipendium</option>');
            $sel.append('<option value="Josef Güttler Stipendium">Josef Güttler Stipendium</option>');
        } else {
            typen.forEach(function(t) {
                var label = t.bezeichnung || t.id;
                $sel.append('<option value="' + escAttr(label) + '" data-runde="' + escAttr(t.runde || '') + '">' + escHtml(label) + '</option>');
            });
        }
    }

    function openManuellModal(editData) {
        if (!$manuellModal) $manuellModal = $('#dgptm-manuell-modal');

        // Felder leeren
        $('#dgptm-manuell-id').val('');
        $('#dgptm-manuell-orcid').val('');
        $('#dgptm-manuell-orcid-status').text('');
        $('#dgptm-manuell-name').val('');
        $('#dgptm-manuell-email').val('');
        $('#dgptm-manuell-institution').val('');
        $('#dgptm-manuell-projekt-titel').val('');
        $('#dgptm-manuell-projekt-zus').val('');
        $('#dgptm-manuell-projekt-meth').val('');
        $('#dgptm-manuell-doc-lebenslauf').val('');
        $('#dgptm-manuell-doc-motivation').val('');
        $('#dgptm-manuell-doc-expose').val('');
        $('#dgptm-manuell-doc-empfehlung').val('');
        $('#dgptm-manuell-doc-studien').val('');
        $('#dgptm-manuell-doc-publikationen').val('');
        $('#dgptm-manuell-doc-zusatz').val('');
        $('#dgptm-manuell-bemerkung').val('');
        $('#dgptm-manuell-eingang').val(new Date().toISOString().slice(0,10));
        $('#dgptm-manuell-status').val('Freigegeben');

        // Default-Runde aus Filter uebernehmen
        var aktuelleRunde = $('#dgptm-vorsitz-runde').val() || (config.defaultRunde || '');
        var aktuellerTyp  = $('#dgptm-vorsitz-runde option:selected').data('typ') || '';
        $('#dgptm-manuell-runde').val(aktuelleRunde);
        if (aktuellerTyp) {
            $('#dgptm-manuell-typ').val(aktuellerTyp);
        }

        if (editData && editData.raw) {
            var r = editData.raw;
            $('#dgptm-manuell-title').text('Bewerbung bearbeiten');
            $('#dgptm-manuell-id').val(r.id || '');
            $('#dgptm-manuell-typ').val(r.stipendientyp || '');
            $('#dgptm-manuell-runde').val(r.runde || '');
            $('#dgptm-manuell-orcid').val(r.bewerber_orcid || '');
            $('#dgptm-manuell-name').val(r.bewerber_name || '');
            $('#dgptm-manuell-email').val(r.bewerber_email || '');
            $('#dgptm-manuell-institution').val(r.bewerber_institution || '');
            $('#dgptm-manuell-projekt-titel').val(r.projekt_titel || '');
            $('#dgptm-manuell-projekt-zus').val(r.projekt_zusammenfassung || '');
            $('#dgptm-manuell-projekt-meth').val(r.projekt_methodik || '');
            $('#dgptm-manuell-bemerkung').val(r.bemerkung || '');
            $('#dgptm-manuell-eingang').val(r.eingangsdatum || '');
            $('#dgptm-manuell-status').val(r.status || 'Freigegeben');

            var d = r.dokument_urls_decoded || {};
            $('#dgptm-manuell-doc-lebenslauf').val(d.lebenslauf || '');
            $('#dgptm-manuell-doc-motivation').val(d.motivationsschreiben || '');
            $('#dgptm-manuell-doc-expose').val(d.expose || '');
            $('#dgptm-manuell-doc-empfehlung').val(d.empfehlungsschreiben || '');
            $('#dgptm-manuell-doc-studien').val(d.studienleistungen || '');
            $('#dgptm-manuell-doc-publikationen').val(d.publikationen || '');
            $('#dgptm-manuell-doc-zusatz').val(d.zusatzqualifikationen || '');
        } else {
            $('#dgptm-manuell-title').text('Antrag manuell einpflegen');
        }

        $manuellModal.fadeIn(150);
    }

    function closeManuellModal() {
        if ($manuellModal) $manuellModal.fadeOut(150);
    }

    function collectManuellData() {
        return {
            stipendientyp:           $('#dgptm-manuell-typ').val(),
            runde:                   $('#dgptm-manuell-runde').val().trim(),
            status:                  $('#dgptm-manuell-status').val(),
            bewerber_orcid:          $('#dgptm-manuell-orcid').val().trim(),
            bewerber_name:           $('#dgptm-manuell-name').val().trim(),
            bewerber_email:          $('#dgptm-manuell-email').val().trim(),
            bewerber_institution:    $('#dgptm-manuell-institution').val().trim(),
            projekt_titel:           $('#dgptm-manuell-projekt-titel').val(),
            projekt_zusammenfassung: $('#dgptm-manuell-projekt-zus').val(),
            projekt_methodik:        $('#dgptm-manuell-projekt-meth').val(),
            bemerkung:               $('#dgptm-manuell-bemerkung').val(),
            eingangsdatum:           $('#dgptm-manuell-eingang').val(),
            dokument_urls: {
                lebenslauf:           $('#dgptm-manuell-doc-lebenslauf').val().trim(),
                motivationsschreiben: $('#dgptm-manuell-doc-motivation').val().trim(),
                expose:               $('#dgptm-manuell-doc-expose').val().trim(),
                empfehlungsschreiben: $('#dgptm-manuell-doc-empfehlung').val().trim(),
                studienleistungen:    $('#dgptm-manuell-doc-studien').val().trim(),
                publikationen:        $('#dgptm-manuell-doc-publikationen').val().trim(),
                zusatzqualifikationen:$('#dgptm-manuell-doc-zusatz').val().trim()
            }
        };
    }

    function saveManuell() {
        var id = $('#dgptm-manuell-id').val();
        var data = collectManuellData();

        if (!data.stipendientyp || !data.runde || !data.bewerber_name) {
            alert('Stipendientyp, Runde und Bewerber-Name sind Pflichtfelder.');
            return;
        }

        var $btn = $('#dgptm-manuell-save');
        $btn.prop('disabled', true).text('Speichern...');

        var action = id ? 'dgptm_stipendium_manual_update' : 'dgptm_stipendium_manual_create';
        var post = { action: action, nonce: nonce, data: JSON.stringify(data) };
        if (id) post.id = id;

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: post,
            success: function(response) {
                if (response.success) {
                    closeManuellModal();
                    loadBewerbungen();
                } else {
                    alert(response.data?.message || response.data || strings.fehler);
                }
            },
            error: function() { alert(strings.fehler || 'Fehler beim Speichern.'); },
            complete: function() {
                $btn.prop('disabled', false).text('Bewerbung speichern');
            }
        });
    }

    function deleteManuell(id) {
        if (!confirm(strings.confirm_delete || 'Wirklich löschen?')) return;
        $.ajax({
            url: ajaxUrl, method: 'POST',
            data: { action: 'dgptm_stipendium_manual_delete', nonce: nonce, id: id },
            success: function(response) {
                if (response.success) {
                    loadBewerbungen();
                } else {
                    alert(response.data?.message || response.data || strings.fehler);
                }
            },
            error: function() { alert(strings.fehler); }
        });
    }

    function editManuell(id) {
        $.ajax({
            url: ajaxUrl, method: 'POST',
            data: { action: 'dgptm_stipendium_manual_get', nonce: nonce, id: id },
            success: function(response) {
                if (response.success) {
                    openManuellModal(response.data);
                } else {
                    alert(response.data?.message || response.data || strings.fehler);
                }
            },
            error: function() { alert(strings.fehler); }
        });
    }

    /* ──────────────────────────────────────────
     * ORCID-Lookup
     * ────────────────────────────────────────── */

    function lookupOrcid() {
        var orcid = $('#dgptm-manuell-orcid').val().trim();
        var $status = $('#dgptm-manuell-orcid-status');
        if (!/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/.test(orcid)) {
            $status.css('color', '#dc2626').text('Ungültiges ORCID-Format. Bitte: 0000-0000-0000-0000');
            return;
        }
        $status.css('color', '#6b7280').text('Daten werden abgerufen...');

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: {
                action: 'dgptm_stipendium_lookup_orcid',
                nonce: config.orcidNonce,
                orcid: orcid
            },
            success: function(response) {
                if (response.success && response.data && response.data.data) {
                    var d = response.data.data;
                    if (d.name && !$('#dgptm-manuell-name').val()) $('#dgptm-manuell-name').val(d.name);
                    if (d.institution && !$('#dgptm-manuell-institution').val()) $('#dgptm-manuell-institution').val(d.institution);
                    if (d.email && !$('#dgptm-manuell-email').val()) $('#dgptm-manuell-email').val(d.email);
                    $status.css('color', '#15803d').text('ORCID-Daten übernommen.');
                } else {
                    var msg = (response.data && response.data.message) ? response.data.message : (strings.orcid_fehler || 'ORCID-Lookup fehlgeschlagen.');
                    $status.css('color', '#dc2626').text(msg);
                }
            },
            error: function() {
                $status.css('color', '#dc2626').text(strings.orcid_fehler || 'Verbindungsfehler.');
            }
        });
    }

    /* ──────────────────────────────────────────
     * Gutachter-Pool im Einladungs-Modal
     * ────────────────────────────────────────── */

    function populateGutachterPool() {
        var pool = config.gutachterPool || [];
        if (!pool.length) {
            $('#dgptm-einladung-pool-wrap').hide();
            return;
        }
        var $sel = $('#dgptm-einladung-pool');
        $sel.find('option:not(:first)').remove();
        pool.forEach(function(g) {
            $sel.append('<option value="' + escAttr(g.gutachter_email) + '" data-name="' + escAttr(g.gutachter_name) + '">'
                + escHtml(g.gutachter_name) + ' &lt;' + escHtml(g.gutachter_email) + '&gt;</option>');
        });
        $('#dgptm-einladung-pool-wrap').show();
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
        // Stipendientypen ins Manuell-Modal + Runden-Modal
        populateTypDropdown();
        populateRundeTypDropdown();
        populateGutachterPool();

        // Initial laden
        loadBewerbungen();
        loadLaufende();

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
                case 'manuell-edit':
                    editManuell(id);
                    break;
                case 'manuell-delete':
                    deleteManuell(id);
                    break;
                case 'erinnern':
                    sendErinnerung(btn.data('token-id'));
                    break;
                case 'pool-edit':
                    var items = $('#dgptm-gutachter-list').data('items') || [];
                    var match = items.find(function(g){ return String(g.id) === String(id); });
                    if (match) openGutachterForm(match);
                    break;
                case 'pool-delete':
                    deleteGutachter(id);
                    break;
            }
        });

        // Einladungs-Modal
        $('#dgptm-einladung-send').on('click', sendEinladung);
        $('#dgptm-einladung-close, #dgptm-einladung-cancel').on('click', function() {
            $('#dgptm-einladung-modal').fadeOut(200);
        });

        // Pool: bei Auswahl Name + Mail uebernehmen
        $('#dgptm-einladung-pool').on('change', function() {
            var $opt = $(this).find('option:selected');
            var email = $(this).val();
            var name  = $opt.data('name') || '';
            if (email) {
                $('#dgptm-einladung-name').val(name);
                $('#dgptm-einladung-email').val(email);
            }
        });

        // Runden-Modal
        $('#dgptm-vorsitz-btn-runde-add').on('click', openRundeModal);
        $('#dgptm-runde-close, #dgptm-runde-cancel').on('click', closeRundeModal);
        $('#dgptm-runde-save').on('click', saveRunde);
        $('#dgptm-runde-typ-id').on('change', function() {
            var $opt = $(this).find('option:selected');
            $('#dgptm-runde-bezeichnung').val($opt.data('bezeichnung') || '');
            // Runden-Bezeichnung leer lassen (neue Runde fuer bestehenden Typ)
        });

        // Gutachter-Stammdaten-Modal
        $('#dgptm-vorsitz-btn-gutachter').on('click', openGutachterModal);
        $('#dgptm-gutachter-close').on('click', closeGutachterModal);
        $('#dgptm-gutachter-new').on('click', function() { openGutachterForm(null); });
        $('#dgptm-gutachter-form-save').on('click', saveGutachter);
        $('#dgptm-gutachter-form-cancel').on('click', closeGutachterForm);
        var gutachterSearchTimer;
        $('#dgptm-gutachter-search').on('input', function() {
            clearTimeout(gutachterSearchTimer);
            var q = $(this).val();
            gutachterSearchTimer = setTimeout(function() { loadGutachterList(q); }, 250);
        });

        // Manuell-Modal
        $('#dgptm-vorsitz-btn-manuell-add').on('click', function() { openManuellModal(null); });
        $('#dgptm-manuell-close, #dgptm-manuell-cancel').on('click', closeManuellModal);
        $('#dgptm-manuell-save').on('click', saveManuell);
        $('#dgptm-manuell-orcid-btn').on('click', lookupOrcid);

        // Modals schliessen bei Klick ausserhalb
        $('#dgptm-einladung-modal, #dgptm-manuell-modal, #dgptm-runde-modal, #dgptm-gutachter-modal').on('click', function(e) {
            if ($(e.target).hasClass('dgptm-vorsitz-modal-overlay')) {
                $(this).fadeOut(200);
            }
        });
    });

})(jQuery);
