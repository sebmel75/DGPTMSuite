<?php
/**
 * Partial: PDF-Import-Tab
 * Enthält den vollständigen 3-Schritte-Wizard für den PDF-Import kompletter Ausgaben:
 * Schritt 1: PDF hochladen, Schritt 2: KI-Extraktion, Schritt 3: Prüfen & Importieren.
 *
 * @package Zeitschrift_Kardiotechnik
 */

if (!defined('ABSPATH')) exit;
?>

<div class="zk-section-wrap" data-section="pdf-import">
    <div class="zk-pdf-import">
        <input type="hidden" id="zk-import-id" value="">

        <!-- Schritt 1: PDF hochladen -->
        <div class="zk-import-step zk-import-step-active" id="zk-import-step-1">
            <div class="zk-step-header">
                <span class="zk-step-number">1</span>
                <h3>Ausgaben-PDF hochladen</h3>
            </div>
            <div class="zk-upload-zone" id="zk-pdf-dropzone">
                <input type="file" id="zk-pdf-file" accept="application/pdf" style="display: none;">
                <div class="zk-upload-content">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="12" y1="18" x2="12" y2="12"></line>
                        <line x1="9" y1="15" x2="15" y2="15"></line>
                    </svg>
                    <p class="zk-upload-text">Komplette Zeitschriften-Ausgabe als PDF hierher ziehen oder <button type="button" class="zk-link-btn" id="zk-pdf-browse">durchsuchen</button></p>
                    <p class="zk-upload-hint">Die Ausgabe wird automatisch mit KI analysiert, Titelseite und Artikel werden extrahiert</p>
                </div>
                <div class="zk-upload-progress" style="display: none;">
                    <div class="zk-progress-bar"><div class="zk-progress-fill"></div></div>
                    <p class="zk-progress-text">Hochladen...</p>
                </div>
            </div>
            <div class="zk-upload-info" id="zk-pdf-info" style="display: none;">
                <div class="zk-file-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                    </svg>
                </div>
                <div class="zk-file-details">
                    <span class="zk-file-name"></span>
                    <span class="zk-file-size"></span>
                </div>
                <button type="button" class="zk-btn zk-btn-danger zk-btn-small" id="zk-pdf-remove">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Schritt 2: KI-Extraktion (Titelseite, Text, Bilder, Artikel-Erkennung) -->
        <div class="zk-import-step" id="zk-import-step-2">
            <div class="zk-step-header">
                <span class="zk-step-number">2</span>
                <h3>KI-Extraktion &amp; Analyse</h3>
                <button type="button" class="zk-btn zk-btn-mini zk-btn-secondary" id="zk-ai-settings-btn" title="KI-Einstellungen">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                    </svg>
                </button>
            </div>
            <div class="zk-step-content">
                <p class="zk-step-description">Die KI extrahiert Titelseite, Text und Bilder, identifiziert alle Artikel und erstellt individuelle PDFs.</p>
                <div class="zk-extraction-status" style="display: none;">
                    <div class="zk-spinner"></div>
                    <span id="zk-extraction-status-text">Extrahiere und analysiere... (kann 2-3 Minuten dauern)</span>
                </div>
                <button type="button" class="zk-btn zk-btn-primary" id="zk-extract-btn" disabled>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                    </svg>
                    Mit KI extrahieren &amp; analysieren
                </button>
            </div>
        </div>

        <!-- Schritt 3: Prüfen & Importieren -->
        <div class="zk-import-step" id="zk-import-step-3">
            <div class="zk-step-header">
                <span class="zk-step-number">3</span>
                <h3>Prüfen &amp; Importieren</h3>
            </div>
            <div class="zk-step-content">
                <div class="zk-import-preview" style="display: none;">
                    <!-- Ausgabe-Metadaten mit Titelseite -->
                    <div class="zk-preview-section">
                        <h4>Ausgabe</h4>
                        <div class="zk-cover-preview">
                            <div class="zk-cover-image" id="zk-issue-cover">
                                <img src="" alt="Titelseite">
                            </div>
                            <div class="zk-issue-meta-form">
                                <div class="zk-form-row">
                                    <div class="zk-form-group">
                                        <label for="zk-issue-jahr">Jahr</label>
                                        <input type="text" id="zk-issue-jahr" class="zk-input" placeholder="2024">
                                    </div>
                                    <div class="zk-form-group">
                                        <label for="zk-issue-ausgabe">Ausgabe</label>
                                        <input type="text" id="zk-issue-ausgabe" class="zk-input" placeholder="1">
                                    </div>
                                </div>
                                <div class="zk-form-group">
                                    <label for="zk-issue-doi">DOI</label>
                                    <input type="text" id="zk-issue-doi" class="zk-input" placeholder="10.1234/...">
                                </div>
                                <div class="zk-extraction-stats">
                                    <div class="zk-stat"><strong id="zk-page-count">0</strong><span>Seiten</span></div>
                                    <div class="zk-stat"><strong id="zk-char-count">0</strong><span>Zeichen</span></div>
                                    <div class="zk-stat"><strong id="zk-image-count">0</strong><span>Bilder</span></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Erkannte Artikel -->
                    <div class="zk-preview-section">
                        <h4>Erkannte Artikel (<span id="zk-article-count">0</span>)</h4>
                        <div class="zk-articles-preview-list" id="zk-articles-preview">
                            <!-- Artikel werden dynamisch eingefügt -->
                        </div>
                    </div>

                    <!-- Aktionen -->
                    <div class="zk-import-actions">
                        <button type="button" class="zk-btn zk-btn-secondary" id="zk-import-discard">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            </svg>
                            Verwerfen
                        </button>
                        <button type="button" class="zk-btn zk-btn-success" id="zk-import-save">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                            Ausgabe importieren
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
