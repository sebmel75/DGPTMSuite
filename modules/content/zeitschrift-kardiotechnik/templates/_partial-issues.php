<?php
/**
 * Partial: Ausgaben-Tab
 * Enthält Filter-Dropdowns + Aktions-Buttons (Neue Ausgabe, Aktualisieren) + Ausgaben-Liste.
 * Die Buttons werden hier (statt im Header) platziert, damit sie in Dashboard-Embed-Mode
 * sichtbar bleiben, wenn der .zk-manager-header ausgeblendet wird.
 *
 * @package Zeitschrift_Kardiotechnik
 */

if (!defined('ABSPATH')) exit;
?>

<div class="zk-section-wrap" data-section="issues">
    <div class="zk-issues-filters" style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center;">
        <select id="zk-filter-status" class="zk-select">
            <option value="">Alle Status</option>
            <option value="online">Online</option>
            <option value="scheduled">Geplant</option>
        </select>
        <select id="zk-filter-year" class="zk-select">
            <option value="">Alle Jahre</option>
        </select>

        <button type="button" class="zk-btn zk-btn-primary" id="zk-new-issue-btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Neue Ausgabe
        </button>
        <button type="button" class="zk-btn zk-btn-secondary" id="zk-refresh-btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="23 4 23 10 17 10"></polyline>
                <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
            </svg>
            Aktualisieren
        </button>
    </div>

    <div class="zk-issues-list" id="zk-issues-list">
        <div class="zk-loading">
            <div class="zk-spinner"></div>
            <span>Lade Ausgaben...</span>
        </div>
    </div>
</div>
