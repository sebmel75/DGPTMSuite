/**
 * DGPTM Formelsammlung - Medizinischer Formelrechner
 * Vanilla JS, keine Abhaengigkeiten ausser optionalem KaTeX.
 * 25 Formeln mit globaler Wertpropagation, Auto-Berechnung, Dirty-Flag.
 *
 * @version 1.0.0
 * @author  Sebastian Melzer / DGPTM
 */
;(function () {
    'use strict';

    /* ============================================================
     *  FORMELDEFINITIONEN (20 medizinisch + 5 technisch)
     * ============================================================ */
    var F = [
        // --- Medizinische Formeln ---
        { id:'blood-volume', params:['height','weight','gender'], unit:'l', produces:'bloodVolume', fmt:0,
          producesTransform: function(r){ return r*1000; },
          compute: function(v){
              var h=v.height/100;
              if(v.gender==='m')   return 0.3669*Math.pow(h,3)+0.03219*v.weight+0.6041;
              if(v.gender==='f') return 0.3561*Math.pow(h,3)+0.03308*v.weight+0.1833;
              return null;
          }},
        { id:'bsa-mosteller', params:['height','weight'], unit:'m\u00B2', produces:'bsa', fmt:2,
          compute: function(v){ return Math.sqrt((v.height*v.weight)/3600); }},
        { id:'bsa-dubois', params:['height','weight'], unit:'m\u00B2', produces:'bsa', fmt:2,
          compute: function(v){ return 0.007184*Math.pow(v.height,0.725)*Math.pow(v.weight,0.425); }},
        { id:'ibw-devine', params:['height','gender'], unit:'kg', produces:'ibw', fmt:1,
          compute: function(v){
              var i=v.height/2.54;
              if(v.gender==='m')   return 50+2.3*(i-60);
              if(v.gender==='f') return 45.5+2.3*(i-60);
              return null;
          }},
        { id:'abw', params:['ibw','weight'], unit:'kg', fmt:1,
          compute: function(v){ return v.ibw+0.4*(v.weight-v.ibw); }},
        { id:'hb-dilution', params:['hb','bloodVolume','primingVol'], unit:'g/dl', fmt:1,
          compute: function(v){ var d=v.bloodVolume+v.primingVol; return d===0?null:(v.hb*v.bloodVolume)/d; }},
        { id:'map', params:['systolic','diastolic'], unit:'mmHg', produces:'map', fmt:0,
          compute: function(v){ return (2*v.diastolic+v.systolic)/3; }},
        { id:'svr', params:['map','cvp','co'], unit:'dyn\u00B7s/cm\u2075', fmt:0,
          compute: function(v){ return v.co===0?null:((v.map-v.cvp)/v.co)*80; }},
        { id:'pvr', params:['mpap','pcwp','co'], unit:'dyn\u00B7s/cm\u2075', fmt:0,
          compute: function(v){ return v.co===0?null:((v.mpap-v.pcwp)/v.co)*80; }},
        { id:'potassium', params:['kTarget','kActual','weight'], unit:'mmol', fmt:1,
          compute: function(v){ return (v.kTarget-v.kActual)*v.weight*0.4; }},
        { id:'nabic', params:['be','weight'], unit:'mmol', fmt:0,
          compute: function(v){ return Math.abs(v.be)*v.weight*0.3; }},
        { id:'tris', params:['be','weight'], unit:'mmol', fmt:0,
          compute: function(v){ return Math.abs(v.be)*v.weight*0.5; }},
        { id:'o2-content', params:['hb','sat','po2'], unit:'ml/dl', produces:'cao2', fmt:0,
          compute: function(v){ return (v.hb*1.34*v.sat/100)+(v.po2*0.003); }},
        { id:'avdo2', params:['cao2','cvo2'], unit:'ml/dl', produces:'avdo2', fmt:2,
          compute: function(v){ return v.cao2-v.cvo2; }},
        { id:'do2', params:['cao2','co','bsa'], unit:'ml/min', produces:'do2', fmt:0,
          compute: function(v){ return v.cao2*v.co*10; },
          extra: function(v,r){ return (!v.bsa||v.bsa===0)?null:
              {label:'DO\u2082I', value:r/v.bsa, unit:'ml/min/m\u00B2'}; }},
        { id:'vo2', params:['avdo2','co','bsa'], unit:'ml/min', produces:'vo2', fmt:0,
          compute: function(v){ return v.avdo2*v.co*10; },
          extra: function(v,r){ return (!v.bsa||v.bsa===0)?null:
              {label:'VO\u2082I', value:r/v.bsa, unit:'ml/min/m\u00B2'}; }},
        { id:'o2er', params:['vo2','do2'], unit:'%', fmt:1,
          compute: function(v){ return v.do2===0?null:(v.vo2/v.do2)*100; }},
        { id:'vco2', params:['co','cvco2','caco2'], unit:'ml/min', fmt:0,
          compute: function(v){ return v.co*(v.cvco2-v.caco2)*10; }},
        { id:'co-fick', params:['vo2','avdo2'], unit:'l/min', produces:'co', fmt:2,
          compute: function(v){ return v.avdo2===0?null:v.vo2/(v.avdo2*10); }},
        { id:'ci', params:['co','bsa'], unit:'l/min/m\u00B2', fmt:2,
          compute: function(v){ return v.bsa===0?null:v.co/v.bsa; }},

        // --- Goal Directed Perfusion ---
        { id:'gdp-flow', params:['bsa','ciTarget'], unit:'l/min', fmt:1,
          compute: function(v){ return v.bsa*v.ciTarget; }},
        { id:'gdp-do2', params:['bsa','do2Target'], unit:'ml/min', fmt:0,
          compute: function(v){ return v.bsa*v.do2Target; }},
        { id:'gdp-min-hb', params:['do2iTarget','ciActual','sat'], unit:'g/dl', fmt:1,
          compute: function(v){ var d=v.ciActual*1.34*(v.sat/100)*10; return d===0?null:v.do2iTarget/d; }},
        { id:'gdp-do2i-actual', params:['hb','sat','ciActual'], unit:'ml/min/m\u00B2', fmt:0,
          compute: function(v){ return v.hb*1.34*(v.sat/100)*v.ciActual*10; }},
        { id:'gdp-flow-for-hb', params:['do2iTarget','bsa','hb','sat'], unit:'l/min', fmt:1,
          compute: function(v){ var d=v.hb*1.34*(v.sat/100)*10; return d===0?null:(v.do2iTarget*v.bsa)/d; }},
        { id:'gdp-transfusion', params:['ciActual','sat'], unit:'g/dl', fmt:1,
          compute: function(v){ var d=v.ciActual*1.34*(v.sat/100)*10; return d===0?null:272/d; }},

        // --- Technische Formeln ---
        { id:'hagen-poiseuille', params:['dp','r','eta','l'], unit:'ml/s', fmt:4,
          compute: function(v){ return (v.eta===0||v.l===0)?null:
              (Math.PI*v.dp*Math.pow(v.r,4))/(8*v.eta*v.l); }},
        { id:'reynolds', params:['rho','v','d','eta'], unit:'', fmt:0,
          compute: function(v){ return v.eta===0?null:(v.rho*v.v*v.d)/v.eta; },
          extra: function(_,r){ return {label:'Str\u00F6mung', value:r<2300?'laminar':'turbulent', unit:''}; }},
        { id:'laplace', params:['p','r','h'], unit:'N/m', fmt:2,
          compute: function(v){ return v.h===0?null:(v.p*v.r)/(2*v.h); }},
        { id:'tmp', params:['pArt','pVen','pDial'], unit:'mmHg', fmt:0,
          compute: function(v){ return ((v.pArt+v.pVen)/2)-v.pDial; }},
        { id:'tube-volume', params:['r','l'], unit:'ml', fmt:2,
          compute: function(v){ return (Math.PI*Math.pow(v.r,2)*v.l)/1000; }}
    ];

    /* ============================================================
     *  ZUSTAND
     * ============================================================ */
    var globalValues = {};          // Geteilte Werte (height, weight, bsa, co, ...)
    var dirtyInputs  = {};          // { 'formulaId:param': true }
    var calcTimer    = null;        // Debounce

    /* ============================================================
     *  HILFSFUNKTIONEN
     * ============================================================ */
    function readNum(el) {
        if (!el) return null;
        var v = parseFloat(el.value);
        return isNaN(v) ? null : v;
    }

    function fmtVal(value, dec) {
        if (value === null || value === undefined || isNaN(value)) return '--';
        var d = (typeof dec === 'number') ? dec : 2;
        if (Math.abs(value) > 1000) d = 0;
        return value.toFixed(d);
    }

    function dKey(fId, param) { return fId + ':' + param; }

    /* ============================================================
     *  PROPAGATION - Globale Werte an Karten-Inputs verteilen
     * ============================================================ */
    function propagate(key, value) {
        globalValues[key] = value;
        var box = document.querySelector('.mc-calculator');
        if (!box) return;

        var sel = '.mc-card input[data-param="'+key+'"], .mc-card select[data-param="'+key+'"]';
        var targets = box.querySelectorAll(sel);
        for (var i = 0; i < targets.length; i++) {
            var inp = targets[i];
            var card = inp.closest('.mc-card');
            if (!card) continue;
            if (dirtyInputs[dKey(card.getAttribute('data-formula'), key)]) continue;

            inp.value = (value !== null && value !== undefined) ? value : '';
            inp.classList.add('mc-autofilled');
        }
    }

    function propagateAll() {
        var keys = Object.keys(globalValues);
        for (var i = 0; i < keys.length; i++) propagate(keys[i], globalValues[keys[i]]);
    }

    /* ============================================================
     *  BERECHNUNG
     * ============================================================ */
    function computeOne(formula) {
        var card = document.querySelector('.mc-card[data-formula="'+formula.id+'"]');
        if (!card) return null;

        // Parameter sammeln
        var vals = {}, ok = true;
        for (var i = 0; i < formula.params.length; i++) {
            var p = formula.params[i];
            var el = card.querySelector('[data-param="'+p+'"]');
            if (el && el.tagName === 'SELECT') {
                vals[p] = el.value || null;
                if (!vals[p]) ok = false;
            } else {
                vals[p] = readNum(el);
                if (vals[p] === null) ok = false;
            }
        }

        var rEl = document.getElementById('result-' + formula.id);
        if (!rEl) return null;

        // Unvollstaendig oder Fehler -> Platzhalter
        if (!ok) { rEl.textContent = '--'; rEl.classList.add('mc-result-placeholder'); return null; }

        var result = formula.compute(vals);
        if (result === null || isNaN(result)) {
            rEl.textContent = '--'; rEl.classList.add('mc-result-placeholder'); return null;
        }

        // Hauptergebnis
        rEl.textContent = fmtVal(result, formula.fmt) + (formula.unit ? ' '+formula.unit : '');
        rEl.classList.remove('mc-result-placeholder');

        // Zusatzanzeige (DO2I, VO2I, Stroemungstyp)
        if (typeof formula.extra === 'function') {
            var ex = formula.extra(vals, result);
            var exEl = document.getElementById('result-'+formula.id+'-extra');
            if (exEl && ex) {
                exEl.textContent = ex.label + ': ' +
                    (typeof ex.value === 'string' ? ex.value : fmtVal(ex.value, 1) + (ex.unit ? ' '+ex.unit : ''));
                exEl.classList.remove('mc-result-placeholder');
            }
        }

        // Produzierter globaler Wert weiterreichen
        if (formula.produces) {
            var pv = (typeof formula.producesTransform === 'function') ? formula.producesTransform(result) : result;
            propagate(formula.produces, pv);
        }
        return result;
    }

    /** Alle Formeln neu berechnen (3 Durchlaeufe fuer Kaskaden) */
    function recalcAll() {
        for (var pass = 0; pass < 3; pass++) {
            for (var i = 0; i < F.length; i++) computeOne(F[i]);
        }
    }

    function scheduleRecalc() {
        if (calcTimer) clearTimeout(calcTimer);
        calcTimer = setTimeout(recalcAll, 150);
    }

    /* ============================================================
     *  EVENT-HANDLING
     * ============================================================ */

    /** Globale Inputs (data-global) ueberwachen */
    function bindGlobalInputs() {
        var els = document.querySelectorAll('.mc-global-inputs [data-global]');
        for (var i = 0; i < els.length; i++) {
            (function (el) {
                var key = el.getAttribute('data-global');
                var handler = function () {
                    propagate(key, el.tagName === 'SELECT' ? (el.value || null) : readNum(el));
                    scheduleRecalc();
                };
                el.addEventListener('input', handler);
                el.addEventListener('change', handler);
            })(els[i]);
        }
    }

    /** Event-Delegation fuer alle Karten-Inputs */
    function bindCardInputs() {
        var box = document.querySelector('.mc-calculator');
        if (!box) return;

        function onInput(e) {
            var t = e.target;
            if (!t.matches || !t.matches('[data-param]')) return;
            var card = t.closest('.mc-card');
            if (!card) return;
            t.classList.remove('mc-autofilled');
            dirtyInputs[dKey(card.getAttribute('data-formula'), t.getAttribute('data-param'))] = true;
            scheduleRecalc();
        }
        box.addEventListener('input', onInput);
        box.addEventListener('change', onInput);
    }

    /* ============================================================
     *  RESET
     * ============================================================ */

    /** Kompletter Reset aller Eingaben und Ergebnisse */
    function bindResetAll() {
        var btn = document.getElementById('mc-reset-all');
        if (!btn) return;

        btn.addEventListener('click', function () {
            if (!confirm('Alle Eingaben und Ergebnisse zur\u00FCcksetzen?')) return;

            globalValues = {};
            dirtyInputs  = {};

            var box = document.querySelector('.mc-calculator');
            if (!box) return;

            // Karten-Inputs leeren
            var inputs = box.querySelectorAll('input[data-param], select[data-param]');
            for (var i = 0; i < inputs.length; i++) {
                inputs[i].tagName === 'SELECT' ? (inputs[i].selectedIndex = 0) : (inputs[i].value = '');
                inputs[i].classList.remove('mc-autofilled');
            }

            // Globale Inputs leeren
            var gInputs = document.querySelectorAll('.mc-global-inputs [data-global]');
            for (var j = 0; j < gInputs.length; j++) {
                gInputs[j].tagName === 'SELECT' ? (gInputs[j].selectedIndex = 0) : (gInputs[j].value = '');
            }

            // Ergebnisse zuruecksetzen
            var res = box.querySelectorAll('.mc-result-value');
            for (var k = 0; k < res.length; k++) {
                res[k].textContent = '--';
                res[k].classList.add('mc-result-placeholder');
            }
        });
    }

    /** Einzelne Karten-Resets (Event-Delegation) */
    function bindCardResets() {
        var box = document.querySelector('.mc-calculator');
        if (!box) return;

        box.addEventListener('click', function (e) {
            var btn = e.target.closest('.mc-card-reset');
            if (!btn) return;
            var card = btn.closest('.mc-card');
            if (!card) return;

            var fId = card.getAttribute('data-formula');

            // Inputs leeren, Dirty-Flags entfernen
            var inputs = card.querySelectorAll('[data-param]');
            for (var i = 0; i < inputs.length; i++) {
                inputs[i].tagName === 'SELECT' ? (inputs[i].selectedIndex = 0) : (inputs[i].value = '');
                inputs[i].classList.remove('mc-autofilled');
                delete dirtyInputs[dKey(fId, inputs[i].getAttribute('data-param'))];
            }

            // Ergebnis + Extra zuruecksetzen
            var rEl = document.getElementById('result-' + fId);
            if (rEl) { rEl.textContent = '--'; rEl.classList.add('mc-result-placeholder'); }
            var exEl = document.getElementById('result-' + fId + '-extra');
            if (exEl) { exEl.textContent = ''; exEl.classList.add('mc-result-placeholder'); }

            // Globale Werte erneut in nicht-dirty Felder schreiben
            propagateAll();
            scheduleRecalc();
        });
    }

    /* ============================================================
     *  KATEX RENDERING
     * ============================================================ */
    function renderKatex() {
        if (typeof katex === 'undefined') return;
        var els = document.querySelectorAll('.mc-formula-display[data-katex]');
        for (var i = 0; i < els.length; i++) {
            var tex = els[i].getAttribute('data-katex');
            if (!tex) continue;
            try { katex.render(tex, els[i], { throwOnError: false, displayMode: true }); }
            catch (_) { els[i].textContent = tex; }
        }
    }

    /* ============================================================
     *  INITIALISIERUNG
     * ============================================================ */
    /** Zoll-Referenz-Buttons (Schlauchvolumen) */
    function bindInchButtons() {
        var btns = document.querySelectorAll('.mc-inch-btn[data-r]');
        for (var i = 0; i < btns.length; i++) {
            btns[i].addEventListener('click', function () {
                var r = parseFloat(this.getAttribute('data-r'));
                if (isNaN(r)) return;
                var card = this.closest('.mc-card');
                if (!card) return;
                var inp = card.querySelector('input[data-param="r"]');
                if (!inp) return;
                inp.value = r;
                inp.classList.remove('mc-autofilled');
                // Aktiven Button markieren
                var siblings = card.querySelectorAll('.mc-inch-btn');
                for (var j = 0; j < siblings.length; j++) siblings[j].classList.remove('mc-inch-active');
                this.classList.add('mc-inch-active');
                scheduleRecalc();
            });
        }
    }

    function init() {
        if (!document.querySelector('.mc-calculator')) return;
        renderKatex();
        bindGlobalInputs();
        bindCardInputs();
        bindResetAll();
        bindCardResets();
        bindInchButtons();
        recalcAll();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
