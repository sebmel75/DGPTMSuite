/**
 * DGPTM Stipendium — Gutachten-Bewertungsbogen
 *
 * Funktionen:
 * - Live-Score-Berechnung bei Notenaenderung
 * - Auto-Save alle 30 Sekunden (nur bei Aenderung)
 * - Submit mit Bestaetigungsdialog
 * - Entwurfsdaten aus PHP vorbelegen
 */
(function($) {
    'use strict';

    // Konfiguration aus PHP (wp_localize_script)
    var config = window.dgptmGutachten || {};
    var ajaxUrl = config.ajaxUrl || '';
    var nonce = config.nonce || '';
    var token = config.token || '';
    var strings = config.strings || {};
    var draftData = config.draftData || {};

    // State
    var hasChanges = false;
    var isSaving = false;
    var isSubmitting = false;
    var autoSaveTimer = null;

    // Gewichtungen
    var GEWICHTUNG = { A: 0.30, B: 0.30, C: 0.25, D: 0.15 };

    /**
     * Score-Berechnung und Anzeige aktualisieren.
     */
    function updateScores() {
        var rubriken = ['A', 'B', 'C', 'D'];
        var gewichteteSumme = 0;
        var alleAusgefuellt = true;

        rubriken.forEach(function(prefix) {
            var noten = [];
            for (var i = 1; i <= 3; i++) {
                var val = parseInt($('#' + prefix + i + '_Note').val()) || 0;
                noten.push(val);
                if (val === 0) alleAusgefuellt = false;
            }

            var avg = noten.reduce(function(a, b) { return a + b; }, 0) / 3.0;
            var gewichtet = avg * GEWICHTUNG[prefix];

            // Rubrik-Score anzeigen
            var rubrikScoreEl = $('[data-rubrik-score="' + prefix + '"] .score-value');
            if (noten.some(function(n) { return n > 0; })) {
                rubrikScoreEl.text(avg.toFixed(2));
            } else {
                rubrikScoreEl.text('--');
            }

            // Gewichteten Teil-Score anzeigen
            $('#score-' + prefix.toLowerCase()).text(gewichtet.toFixed(2));
            gewichteteSumme += gewichtet;
        });

        // Gesamt anzeigen
        if (alleAusgefuellt) {
            $('#dgptm-score-gesamt').text(gewichteteSumme.toFixed(2));
            $('#dgptm-score-punkte').text((gewichteteSumme * 10).toFixed(1));
            $('#dgptm-score-vorschau').addClass('dgptm-score-vorschau--complete');
        } else {
            var teilwert = gewichteteSumme > 0 ? gewichteteSumme.toFixed(2) : '--';
            $('#dgptm-score-gesamt').text(teilwert);
            $('#dgptm-score-punkte').text(gewichteteSumme > 0 ? (gewichteteSumme * 10).toFixed(1) : '--');
            $('#dgptm-score-vorschau').removeClass('dgptm-score-vorschau--complete');
        }
    }

    /**
     * Alle Formulardaten als Objekt sammeln.
     */
    function collectFormData() {
        var data = {};
        var notenFelder = ['A1_Note','A2_Note','A3_Note','B1_Note','B2_Note','B3_Note',
                           'C1_Note','C2_Note','C3_Note','D1_Note','D2_Note','D3_Note'];
        var kommentarFelder = ['A_Kommentar','B_Kommentar','C_Kommentar','D_Kommentar','Gesamtanmerkungen'];

        notenFelder.forEach(function(feld) {
            data[feld] = parseInt($('#' + feld).val()) || 0;
        });

        kommentarFelder.forEach(function(feld) {
            data[feld] = $('#' + feld).val() || '';
        });

        return data;
    }

    /**
     * Auto-Save: Entwurf per AJAX speichern.
     */
    function autoSave() {
        if (!hasChanges || isSaving || isSubmitting) return;

        isSaving = true;
        hasChanges = false;
        var statusEl = $('#dgptm-autosave-status');
        statusEl.text(strings.saving || 'Speichere...').addClass('dgptm-autosave--saving');

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: {
                action: 'dgptm_stipendium_autosave',
                nonce: nonce,
                token: token,
                data: JSON.stringify(collectFormData())
            },
            success: function(response) {
                if (response.success) {
                    var savedText = (strings.saved || 'Entwurf gespeichert um') + ' ' + response.data.saved_at;
                    statusEl.text(savedText)
                        .removeClass('dgptm-autosave--saving dgptm-autosave--error')
                        .addClass('dgptm-autosave--saved');
                } else {
                    statusEl.text(response.data?.message || strings.save_error || 'Fehler beim Speichern.')
                        .removeClass('dgptm-autosave--saving dgptm-autosave--saved')
                        .addClass('dgptm-autosave--error');
                    hasChanges = true; // Erneut versuchen
                }
            },
            error: function() {
                statusEl.text(strings.save_error || 'Fehler beim Speichern.')
                    .removeClass('dgptm-autosave--saving dgptm-autosave--saved')
                    .addClass('dgptm-autosave--error');
                hasChanges = true;
            },
            complete: function() {
                isSaving = false;
            }
        });
    }

    /**
     * Gutachten abschliessen.
     */
    function submitGutachten(e) {
        e.preventDefault();

        if (isSubmitting) return;

        // Pflichtfelder pruefen
        var incomplete = false;
        $('.dgptm-gutachten-note').each(function() {
            if (!$(this).val()) {
                $(this).addClass('dgptm-gutachten-note--error');
                incomplete = true;
            } else {
                $(this).removeClass('dgptm-gutachten-note--error');
            }
        });

        if (incomplete) {
            alert('Bitte vergeben Sie fuer alle Leitfragen eine Note zwischen 1 und 10.');
            // Zum ersten fehlenden Feld scrollen
            var firstError = $('.dgptm-gutachten-note--error').first();
            if (firstError.length) {
                $('html, body').animate({ scrollTop: firstError.offset().top - 100 }, 400);
            }
            return;
        }

        // Bestaetigung
        if (!confirm(strings.confirm_submit || 'Moechten Sie Ihr Gutachten jetzt abschliessen? Nach dem Abschluss kann die Bewertung nicht mehr geaendert werden.')) {
            return;
        }

        isSubmitting = true;
        var submitBtn = $('#dgptm-gutachten-submit');
        var originalText = submitBtn.text();
        submitBtn.text(strings.submitting || 'Wird uebermittelt...').prop('disabled', true);

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: {
                action: 'dgptm_stipendium_submit_gutachten',
                nonce: nonce,
                token: token,
                data: JSON.stringify(collectFormData())
            },
            success: function(response) {
                if (response.success) {
                    // Seite neu laden → Danke-Seite wird angezeigt
                    window.location.reload();
                } else {
                    alert(response.data?.message || strings.submit_error || 'Fehler beim Uebermitteln.');
                    submitBtn.text(originalText).prop('disabled', false);
                    isSubmitting = false;
                }
            },
            error: function() {
                alert(strings.submit_error || 'Fehler beim Uebermitteln. Bitte versuchen Sie es erneut.');
                submitBtn.text(originalText).prop('disabled', false);
                isSubmitting = false;
            }
        });
    }

    /**
     * Entwurfsdaten in Formular vorbelegen.
     */
    function restoreDraft() {
        if (!draftData || typeof draftData !== 'object') return;

        Object.keys(draftData).forEach(function(key) {
            var el = $('#' + key);
            if (el.length) {
                el.val(draftData[key]);
            }
        });

        updateScores();
    }

    /**
     * Initialisierung.
     */
    $(document).ready(function() {
        // Entwurf wiederherstellen
        restoreDraft();

        // Score bei jeder Aenderung aktualisieren
        $(document).on('change', '.dgptm-gutachten-note', function() {
            updateScores();
            hasChanges = true;
        });

        // Aenderungen an Textfeldern tracken
        $(document).on('input', '.dgptm-gutachten-form textarea', function() {
            hasChanges = true;
        });

        // Auto-Save alle 30 Sekunden
        autoSaveTimer = setInterval(autoSave, 30000);

        // Submit-Handler
        $('#dgptm-gutachten-form').on('submit', submitGutachten);

        // Warnung bei ungesicherten Aenderungen
        $(window).on('beforeunload', function() {
            if (hasChanges && !isSubmitting) {
                return 'Sie haben ungespeicherte Aenderungen. Moechten Sie die Seite wirklich verlassen?';
            }
        });

        // Initiales Score-Update
        updateScores();
    });

})(jQuery);
