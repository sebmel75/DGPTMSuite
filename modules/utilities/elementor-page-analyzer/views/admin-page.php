<?php
if (!defined('ABSPATH')) exit;
?>
<div class="wrap dgptm-analyzer-wrap">
    <h1>Elementor Page Analyzer</h1>
    <p class="description">Analysiert eine Elementor-Seite und erzeugt ein semantisches Blueprint mit Sichtbarkeitsbedingungen, Shortcodes und Berechtigungsmatrix. Das Blueprint wird als JSON in <code>guides/</code> gespeichert und kann von Claude Code gelesen werden.</p>

    <div class="dgptm-analyzer-card">
        <h2>Seite auswaehlen</h2>
        <div class="dgptm-analyzer-select-wrap">
            <select id="dgptm-analyzer-page" class="regular-text">
                <option value="">Elementor-Seiten werden geladen...</option>
            </select>
            <button id="dgptm-analyzer-run" class="button button-primary" disabled>Blueprint erstellen</button>
        </div>
    </div>

    <div id="dgptm-analyzer-status" class="dgptm-analyzer-card" style="display:none;">
        <h2>Status</h2>
        <div id="dgptm-analyzer-status-text"></div>
    </div>

    <div id="dgptm-analyzer-result" class="dgptm-analyzer-card" style="display:none;">
        <h2>Ergebnis</h2>
        <div id="dgptm-analyzer-summary"></div>
        <h3>Blueprint-Vorschau</h3>
        <textarea id="dgptm-analyzer-json" rows="30" readonly class="large-text code"></textarea>
        <p style="margin-top: 10px;">
            <button id="dgptm-analyzer-copy" class="button">In Zwischenablage kopieren</button>
        </p>
    </div>
</div>
