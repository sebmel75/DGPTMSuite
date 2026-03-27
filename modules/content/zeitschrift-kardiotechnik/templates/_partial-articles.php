<?php
/**
 * Partial: Artikel-Tab
 * Enthält Such-/Sortier-/Filterleiste, Neuer-Artikel-Button und die Artikel-Liste.
 *
 * @package Zeitschrift_Kardiotechnik
 */

if (!defined('ABSPATH')) exit;
?>

<div class="zk-section-wrap" data-section="articles">
    <div class="zk-articles-header">
        <div class="zk-articles-filters">
            <input type="text" id="zk-article-search" class="zk-input" placeholder="Artikel suchen...">
            <select id="zk-article-sort" class="zk-select">
                <option value="date">Erstellungsdatum</option>
                <option value="modified">Änderungsdatum</option>
                <option value="title">Titel</option>
            </select>
            <select id="zk-article-order" class="zk-select">
                <option value="DESC">Neueste zuerst</option>
                <option value="ASC">Älteste zuerst</option>
            </select>
        </div>
        <button type="button" class="zk-btn zk-btn-primary" id="zk-new-article-btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Neuer Artikel
        </button>
    </div>
    <div class="zk-articles-list" id="zk-articles-list">
        <div class="zk-loading">
            <div class="zk-spinner"></div>
            <span>Lade Artikel...</span>
        </div>
    </div>
</div>
