<?php
/**
 * Template: Zeitschrift Verwaltung (Frontend)
 * Komplett AJAX-basiert für Benutzer ohne Backend-Zugriff
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_user = wp_get_current_user();
?>

<div class="zk-manager" id="zk-manager">
    <!-- Header -->
    <div class="zk-manager-header">
        <div class="zk-manager-title">
            <h2>Zeitschrift Verwaltung</h2>
            <span class="zk-manager-user">Angemeldet: <?php echo esc_html($current_user->display_name); ?></span>
        </div>
        <div class="zk-manager-actions">
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
    </div>

    <!-- Tabs -->
    <div class="zk-manager-tabs">
        <button type="button" class="zk-tab zk-tab-active" data-tab="issues">
            Ausgaben
        </button>
        <button type="button" class="zk-tab" data-tab="articles">
            Artikel
        </button>
        <button type="button" class="zk-tab" data-tab="accepted">
            Akzeptierte Einreichungen
        </button>
        <button type="button" class="zk-tab" data-tab="pdf-import">
            PDF-Import
        </button>
    </div>

    <!-- Tab: Ausgaben -->
    <div class="zk-tab-content zk-tab-active" id="zk-tab-issues">
        <div class="zk-issues-filters">
            <select id="zk-filter-status" class="zk-select">
                <option value="">Alle Status</option>
                <option value="online">Online</option>
                <option value="scheduled">Geplant</option>
            </select>
            <select id="zk-filter-year" class="zk-select">
                <option value="">Alle Jahre</option>
            </select>
        </div>

        <div class="zk-issues-list" id="zk-issues-list">
            <div class="zk-loading">
                <div class="zk-spinner"></div>
                <span>Lade Ausgaben...</span>
            </div>
        </div>
    </div>

    <!-- Tab: Artikel -->
    <div class="zk-tab-content" id="zk-tab-articles">
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

    <!-- Tab: Akzeptierte Einreichungen -->
    <div class="zk-tab-content" id="zk-tab-accepted">
        <div class="zk-accepted-list" id="zk-accepted-list">
            <div class="zk-loading">
                <div class="zk-spinner"></div>
                <span>Lade Einreichungen...</span>
            </div>
        </div>
    </div>

    <!-- Tab: PDF-Import -->
    <div class="zk-tab-content" id="zk-tab-pdf-import">
        <div class="zk-pdf-import">
            <!-- Schritt 1: PDF hochladen -->
            <div class="zk-import-step zk-import-step-active" id="zk-import-step-1">
                <div class="zk-step-header">
                    <span class="zk-step-number">1</span>
                    <h3>PDF hochladen</h3>
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
                        <p class="zk-upload-text">PDF-Datei hierher ziehen oder <button type="button" class="zk-link-btn" id="zk-pdf-browse">durchsuchen</button></p>
                        <p class="zk-upload-hint">Unterstützt werden wissenschaftliche Artikel im PDF-Format</p>
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

            <!-- Schritt 2: Text extrahieren -->
            <div class="zk-import-step" id="zk-import-step-2">
                <div class="zk-step-header">
                    <span class="zk-step-number">2</span>
                    <h3>Inhalt extrahieren</h3>
                </div>
                <div class="zk-step-content">
                    <p class="zk-step-description">Der Text und die Bilder werden aus dem PDF extrahiert.</p>
                    <div class="zk-extraction-status" style="display: none;">
                        <div class="zk-spinner"></div>
                        <span>Extrahiere Inhalte...</span>
                    </div>
                    <div class="zk-extraction-result" style="display: none;">
                        <div class="zk-extraction-stats">
                            <span class="zk-stat"><strong id="zk-page-count">0</strong> Seiten</span>
                            <span class="zk-stat"><strong id="zk-char-count">0</strong> Zeichen</span>
                            <span class="zk-stat"><strong id="zk-image-count">0</strong> Bilder</span>
                        </div>
                        <div class="zk-extracted-preview">
                            <label>Vorschau des extrahierten Textes:</label>
                            <textarea id="zk-extracted-text" rows="6" readonly></textarea>
                        </div>
                    </div>
                    <button type="button" class="zk-btn zk-btn-primary" id="zk-extract-btn" disabled>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="16 18 22 12 16 6"></polyline>
                            <polyline points="8 6 2 12 8 18"></polyline>
                        </svg>
                        Text extrahieren
                    </button>
                </div>
            </div>

            <!-- Schritt 3: KI-Analyse -->
            <div class="zk-import-step" id="zk-import-step-3">
                <div class="zk-step-header">
                    <span class="zk-step-number">3</span>
                    <h3>KI-Analyse</h3>
                    <button type="button" class="zk-btn zk-btn-mini zk-btn-secondary" id="zk-ai-settings-btn" title="KI-Einstellungen">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="3"></circle>
                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                        </svg>
                    </button>
                </div>
                <div class="zk-step-content">
                    <p class="zk-step-description">Die KI analysiert den Text und extrahiert automatisch Titel, Autoren, Abstract und weitere Metadaten.</p>
                    <div class="zk-ai-status" style="display: none;">
                        <div class="zk-spinner"></div>
                        <span>KI analysiert den Text...</span>
                    </div>
                    <button type="button" class="zk-btn zk-btn-primary" id="zk-analyze-btn" disabled>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                        </svg>
                        Mit KI analysieren
                    </button>
                </div>
            </div>

            <!-- Schritt 4: Ergebnisse prüfen und importieren -->
            <div class="zk-import-step" id="zk-import-step-4">
                <div class="zk-step-header">
                    <span class="zk-step-number">4</span>
                    <h3>Prüfen & Importieren</h3>
                </div>
                <div class="zk-step-content">
                    <div class="zk-import-preview" style="display: none;">
                        <div class="zk-preview-section">
                            <h4>Grunddaten</h4>
                            <div class="zk-form-group">
                                <label for="zk-import-title">Titel</label>
                                <input type="text" id="zk-import-title" class="zk-input">
                                <span class="zk-confidence" data-field="title"></span>
                            </div>
                            <div class="zk-form-group">
                                <label for="zk-import-subtitle">Untertitel</label>
                                <input type="text" id="zk-import-subtitle" class="zk-input">
                            </div>
                            <div class="zk-form-row">
                                <div class="zk-form-group">
                                    <label for="zk-import-type">Publikationsart</label>
                                    <select id="zk-import-type" class="zk-select">
                                        <option value="">-- Bitte wählen --</option>
                                        <option value="Fachartikel">Fachartikel</option>
                                        <option value="Editorial">Editorial</option>
                                        <option value="Journal Club">Journal Club</option>
                                        <option value="Tutorial">Tutorial</option>
                                        <option value="Fallbericht">Fallbericht</option>
                                        <option value="Übersichtsarbeit">Übersichtsarbeit</option>
                                        <option value="Kommentar">Kommentar</option>
                                    </select>
                                </div>
                                <div class="zk-form-group">
                                    <label for="zk-import-doi">DOI</label>
                                    <input type="text" id="zk-import-doi" class="zk-input">
                                </div>
                            </div>
                        </div>

                        <div class="zk-preview-section">
                            <h4>Autoren</h4>
                            <div class="zk-form-group">
                                <label for="zk-import-authors">Autoren</label>
                                <input type="text" id="zk-import-authors" class="zk-input">
                                <span class="zk-confidence" data-field="authors"></span>
                            </div>
                            <div class="zk-form-group">
                                <label for="zk-import-main-author">Hauptautor/in</label>
                                <input type="text" id="zk-import-main-author" class="zk-input">
                            </div>
                        </div>

                        <div class="zk-preview-section">
                            <h4>Abstracts</h4>
                            <div class="zk-form-row">
                                <div class="zk-form-group">
                                    <label for="zk-import-abstract-de">Zusammenfassung (Deutsch)</label>
                                    <textarea id="zk-import-abstract-de" class="zk-textarea" rows="4"></textarea>
                                    <span class="zk-confidence" data-field="abstract"></span>
                                </div>
                                <div class="zk-form-group">
                                    <label for="zk-import-abstract-en">Abstract (English)</label>
                                    <textarea id="zk-import-abstract-en" class="zk-textarea" rows="4"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="zk-preview-section">
                            <h4>Keywords</h4>
                            <div class="zk-form-row">
                                <div class="zk-form-group">
                                    <label for="zk-import-keywords-de">Keywords (Deutsch)</label>
                                    <input type="text" id="zk-import-keywords-de" class="zk-input">
                                </div>
                                <div class="zk-form-group">
                                    <label for="zk-import-keywords-en">Keywords (English)</label>
                                    <input type="text" id="zk-import-keywords-en" class="zk-input">
                                </div>
                            </div>
                        </div>

                        <div class="zk-preview-section">
                            <h4>Artikelinhalt</h4>
                            <div class="zk-form-group">
                                <label for="zk-import-content">Haupttext (HTML)</label>
                                <textarea id="zk-import-content" class="zk-textarea zk-content-editor" rows="10"></textarea>
                            </div>
                        </div>

                        <div class="zk-preview-section">
                            <h4>Literatur</h4>
                            <div class="zk-form-group">
                                <label for="zk-import-references">Literaturverzeichnis</label>
                                <textarea id="zk-import-references" class="zk-textarea" rows="4"></textarea>
                            </div>
                        </div>

                        <div class="zk-preview-section" id="zk-import-images-section" style="display: none;">
                            <h4>Extrahierte Bilder</h4>
                            <div class="zk-images-grid" id="zk-import-images"></div>
                        </div>

                        <div class="zk-import-actions">
                            <button type="button" class="zk-btn zk-btn-secondary" id="zk-import-reset">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="1 4 1 10 7 10"></polyline>
                                    <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>
                                </svg>
                                Zurücksetzen
                            </button>
                            <button type="button" class="zk-btn zk-btn-success" id="zk-import-save">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                    <polyline points="7 3 7 8 15 8"></polyline>
                                </svg>
                                Als Entwurf speichern
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: KI-Einstellungen -->
    <div class="zk-modal" id="zk-modal-ai-settings">
        <div class="zk-modal-overlay"></div>
        <div class="zk-modal-content">
            <div class="zk-modal-header">
                <h3>KI-Einstellungen</h3>
                <button type="button" class="zk-modal-close">&times;</button>
            </div>
            <div class="zk-modal-body">
                <form id="zk-ai-settings-form">
                    <div class="zk-form-group">
                        <label for="zk-ai-provider">KI-Provider</label>
                        <select id="zk-ai-provider" class="zk-select">
                            <option value="anthropic">Anthropic (Claude)</option>
                            <option value="openai">OpenAI (GPT-4)</option>
                        </select>
                    </div>
                    <div class="zk-form-group">
                        <label for="zk-ai-model">Modell</label>
                        <select id="zk-ai-model" class="zk-select">
                            <option value="claude-sonnet-4-20250514">Claude Sonnet 4 (empfohlen)</option>
                            <option value="claude-3-5-sonnet-20241022">Claude 3.5 Sonnet</option>
                            <option value="claude-3-opus-20240229">Claude 3 Opus</option>
                            <option value="gpt-4-turbo-preview">GPT-4 Turbo</option>
                        </select>
                    </div>
                    <div class="zk-form-group">
                        <label for="zk-ai-key">API-Key</label>
                        <input type="password" id="zk-ai-key" class="zk-input" placeholder="sk-...">
                        <small id="zk-ai-key-status"></small>
                    </div>
                </form>
            </div>
            <div class="zk-modal-footer">
                <button type="button" class="zk-btn zk-btn-secondary zk-modal-cancel">Abbrechen</button>
                <button type="button" class="zk-btn zk-btn-primary" id="zk-save-ai-settings">Speichern</button>
            </div>
        </div>
    </div>

    <!-- Modal: Neue Ausgabe -->
    <div class="zk-modal" id="zk-modal-new">
        <div class="zk-modal-overlay"></div>
        <div class="zk-modal-content">
            <div class="zk-modal-header">
                <h3>Neue Ausgabe erstellen</h3>
                <button type="button" class="zk-modal-close">&times;</button>
            </div>
            <div class="zk-modal-body">
                <form id="zk-new-issue-form">
                    <div class="zk-form-row">
                        <div class="zk-form-group">
                            <label for="zk-new-jahr">Jahr *</label>
                            <input type="number" id="zk-new-jahr" name="jahr" min="2000" max="2100"
                                   value="<?php echo date('Y'); ?>" required>
                        </div>
                        <div class="zk-form-group">
                            <label for="zk-new-ausgabe">Ausgabe *</label>
                            <input type="number" id="zk-new-ausgabe" name="ausgabe" min="1" max="12" required>
                        </div>
                    </div>
                    <div class="zk-form-group">
                        <label for="zk-new-title">Titel</label>
                        <input type="text" id="zk-new-title" name="title" placeholder="z.B. Kardiotechnik 2024/3">
                    </div>
                    <div class="zk-form-group">
                        <label for="zk-new-doi">DOI</label>
                        <input type="text" id="zk-new-doi" name="doi" placeholder="10.xxxx/xxxxx">
                    </div>
                    <div class="zk-form-group">
                        <label for="zk-new-date">Veröffentlichungsdatum</label>
                        <input type="date" id="zk-new-date" name="verfuegbar_ab">
                        <small>Leer lassen für sofortige Veröffentlichung</small>
                    </div>
                </form>
            </div>
            <div class="zk-modal-footer">
                <button type="button" class="zk-btn zk-btn-secondary zk-modal-cancel">Abbrechen</button>
                <button type="button" class="zk-btn zk-btn-primary" id="zk-save-new-issue">
                    Ausgabe erstellen
                </button>
            </div>
        </div>
    </div>

    <!-- Modal: Ausgabe bearbeiten -->
    <div class="zk-modal" id="zk-modal-edit">
        <div class="zk-modal-overlay"></div>
        <div class="zk-modal-content zk-modal-large">
            <div class="zk-modal-header">
                <h3>Ausgabe bearbeiten</h3>
                <button type="button" class="zk-modal-close">&times;</button>
            </div>
            <div class="zk-modal-body">
                <form id="zk-edit-issue-form">
                    <input type="hidden" id="zk-edit-id" name="post_id">

                    <div class="zk-edit-layout">
                        <div class="zk-edit-main">
                            <div class="zk-form-row">
                                <div class="zk-form-group">
                                    <label for="zk-edit-jahr">Jahr</label>
                                    <input type="number" id="zk-edit-jahr" name="jahr" min="2000" max="2100">
                                </div>
                                <div class="zk-form-group">
                                    <label for="zk-edit-ausgabe">Ausgabe</label>
                                    <input type="number" id="zk-edit-ausgabe" name="ausgabe" min="1" max="12">
                                </div>
                            </div>
                            <div class="zk-form-group">
                                <label for="zk-edit-title">Titel</label>
                                <input type="text" id="zk-edit-title" name="title">
                            </div>
                            <div class="zk-form-group">
                                <label for="zk-edit-doi">DOI</label>
                                <input type="text" id="zk-edit-doi" name="doi">
                            </div>
                            <div class="zk-form-group">
                                <label for="zk-edit-date">Veröffentlichungsdatum</label>
                                <input type="date" id="zk-edit-date" name="verfuegbar_ab">
                            </div>
                        </div>

                        <div class="zk-edit-articles">
                            <h4>Verknüpfte Artikel</h4>
                            <div class="zk-linked-articles" id="zk-linked-articles">
                                <!-- Wird per AJAX geladen -->
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="zk-modal-footer">
                <button type="button" class="zk-btn zk-btn-secondary zk-modal-cancel">Abbrechen</button>
                <button type="button" class="zk-btn zk-btn-primary" id="zk-save-edit-issue">
                    Speichern
                </button>
            </div>
        </div>
    </div>

    <!-- Modal: Neuer Artikel -->
    <div class="zk-modal" id="zk-modal-new-article">
        <div class="zk-modal-overlay"></div>
        <div class="zk-modal-content zk-modal-xlarge">
            <div class="zk-modal-header">
                <h3>Neuen Artikel erstellen</h3>
                <button type="button" class="zk-modal-close">&times;</button>
            </div>
            <div class="zk-modal-body">
                <form id="zk-new-article-form">
                    <!-- Grunddaten -->
                    <div class="zk-form-section">
                        <h4 class="zk-form-section-title">Grunddaten</h4>

                        <div class="zk-form-group">
                            <label for="zk-article-title">Titel *</label>
                            <input type="text" id="zk-article-title" name="title" required>
                        </div>

                        <div class="zk-form-group">
                            <label for="zk-article-subtitle">Unterüberschrift</label>
                            <input type="text" id="zk-article-subtitle" name="unterueberschrift">
                        </div>

                        <div class="zk-form-row">
                            <div class="zk-form-group">
                                <label for="zk-article-type">Art der Publikation *</label>
                                <select id="zk-article-type" name="publikationsart" class="zk-select zk-type-select">
                                    <option value="">-- Bitte wählen --</option>
                                    <option value="Fachartikel">Fachartikel</option>
                                    <option value="Editorial">Editorial</option>
                                    <option value="Journal Club">Journal Club</option>
                                    <option value="Tutorial">Tutorial</option>
                                    <option value="Fallbericht">Fallbericht</option>
                                    <option value="Übersichtsarbeit">Übersichtsarbeit</option>
                                    <option value="Kommentar">Kommentar</option>
                                    <option value="Supplement">Supplement</option>
                                    <option value="Externer Fachartikel">Externer Fachartikel</option>
                                </select>
                            </div>
                            <div class="zk-form-group">
                                <label for="zk-article-doi">DOI</label>
                                <input type="text" id="zk-article-doi" name="doi" placeholder="10.xxxx/xxxxx">
                            </div>
                        </div>

                        <div class="zk-form-row">
                            <div class="zk-form-group">
                                <label for="zk-article-kardiotechnik">Kardiotechnik Ausgabe</label>
                                <input type="text" id="zk-article-kardiotechnik" name="kardiotechnikausgabe" placeholder="z.B. 2024/3">
                            </div>
                            <div class="zk-form-group">
                                <label for="zk-article-supplement">Supplement</label>
                                <input type="text" id="zk-article-supplement" name="supplement">
                            </div>
                        </div>
                    </div>

                    <!-- Autoren -->
                    <div class="zk-form-section">
                        <h4 class="zk-form-section-title">Autoren</h4>

                        <div class="zk-form-group">
                            <label for="zk-article-authors">Autoren</label>
                            <input type="text" id="zk-article-authors" name="autoren" placeholder="z.B. Müller A, Schmidt B, Weber C">
                        </div>

                        <div class="zk-form-group">
                            <label for="zk-article-main-author">Hauptautor/in (Korrespondenz)</label>
                            <input type="text" id="zk-article-main-author" name="hauptautorin">
                        </div>
                    </div>

                    <!-- Inhalt -->
                    <div class="zk-form-section">
                        <h4 class="zk-form-section-title">Inhalt</h4>

                        <div class="zk-form-group">
                            <label for="zk-article-content">Artikeltext</label>
                            <textarea id="zk-article-content" name="content" rows="10" class="zk-html-editor"></textarea>
                        </div>

                        <div class="zk-form-group">
                            <label for="zk-article-literatur">Literaturverzeichnis</label>
                            <textarea id="zk-article-literatur" name="literatur" rows="4" placeholder="Literaturangaben..."></textarea>
                        </div>
                    </div>

                    <!-- Abstracts & Keywords -->
                    <div class="zk-form-section">
                        <h4 class="zk-form-section-title">Abstracts & Keywords</h4>

                        <div class="zk-form-row">
                            <div class="zk-form-group">
                                <label for="zk-article-abstract">Zusammenfassung (Deutsch)</label>
                                <textarea id="zk-article-abstract" name="abstract_deutsch" rows="4"></textarea>
                            </div>
                            <div class="zk-form-group">
                                <label for="zk-article-abstract-en">Abstract (English)</label>
                                <textarea id="zk-article-abstract-en" name="abstract" rows="4"></textarea>
                            </div>
                        </div>

                        <div class="zk-form-row">
                            <div class="zk-form-group">
                                <label for="zk-article-keywords">Keywords (Deutsch)</label>
                                <input type="text" id="zk-article-keywords" name="keywords_deutsch" placeholder="Kommagetrennt">
                            </div>
                            <div class="zk-form-group">
                                <label for="zk-article-keywords-en">Keywords (English)</label>
                                <input type="text" id="zk-article-keywords-en" name="keywords_englisch" placeholder="Comma separated">
                            </div>
                        </div>
                    </div>

                    <!-- Optionen -->
                    <div class="zk-form-section">
                        <h4 class="zk-form-section-title">Optionen</h4>

                        <div class="zk-form-group">
                            <label class="zk-checkbox-label">
                                <input type="checkbox" id="zk-article-fulltext" name="volltext_anzeigen" value="1">
                                Volltext anzeigen
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="zk-modal-footer">
                <button type="button" class="zk-btn zk-btn-secondary zk-modal-cancel">Abbrechen</button>
                <button type="button" class="zk-btn zk-btn-primary" id="zk-save-new-article">
                    Artikel erstellen
                </button>
            </div>
        </div>
    </div>

    <!-- Modal: Artikel bearbeiten -->
    <div class="zk-modal" id="zk-modal-edit-article">
        <div class="zk-modal-overlay"></div>
        <div class="zk-modal-content zk-modal-xlarge">
            <div class="zk-modal-header">
                <h3>Artikel bearbeiten</h3>
                <button type="button" class="zk-modal-close">&times;</button>
            </div>
            <div class="zk-modal-body">
                <form id="zk-edit-article-form">
                    <input type="hidden" id="zk-edit-article-id" name="article_id">

                    <!-- Grunddaten -->
                    <div class="zk-form-section">
                        <h4 class="zk-form-section-title">Grunddaten</h4>

                        <div class="zk-form-group">
                            <label for="zk-edit-article-title">Titel *</label>
                            <input type="text" id="zk-edit-article-title" name="title" required>
                        </div>

                        <div class="zk-form-group">
                            <label for="zk-edit-article-subtitle">Unterüberschrift</label>
                            <input type="text" id="zk-edit-article-subtitle" name="unterueberschrift">
                        </div>

                        <div class="zk-form-row">
                            <div class="zk-form-group">
                                <label for="zk-edit-article-type">Art der Publikation *</label>
                                <select id="zk-edit-article-type" name="publikationsart" class="zk-select zk-type-select">
                                    <option value="">-- Bitte wählen --</option>
                                    <option value="Fachartikel">Fachartikel</option>
                                    <option value="Editorial">Editorial</option>
                                    <option value="Journal Club">Journal Club</option>
                                    <option value="Tutorial">Tutorial</option>
                                    <option value="Fallbericht">Fallbericht</option>
                                    <option value="Übersichtsarbeit">Übersichtsarbeit</option>
                                    <option value="Kommentar">Kommentar</option>
                                    <option value="Supplement">Supplement</option>
                                    <option value="Externer Fachartikel">Externer Fachartikel</option>
                                </select>
                            </div>
                            <div class="zk-form-group">
                                <label for="zk-edit-article-doi">DOI</label>
                                <input type="text" id="zk-edit-article-doi" name="doi">
                            </div>
                        </div>

                        <div class="zk-form-row">
                            <div class="zk-form-group">
                                <label for="zk-edit-article-kardiotechnik">Kardiotechnik Ausgabe</label>
                                <input type="text" id="zk-edit-article-kardiotechnik" name="kardiotechnikausgabe" placeholder="z.B. 2024/3">
                            </div>
                            <div class="zk-form-group">
                                <label for="zk-edit-article-supplement">Supplement</label>
                                <input type="text" id="zk-edit-article-supplement" name="supplement">
                            </div>
                        </div>
                    </div>

                    <!-- Autoren -->
                    <div class="zk-form-section">
                        <h4 class="zk-form-section-title">Autoren</h4>

                        <div class="zk-form-group">
                            <label for="zk-edit-article-authors">Autoren</label>
                            <input type="text" id="zk-edit-article-authors" name="autoren">
                        </div>

                        <div class="zk-form-group">
                            <label for="zk-edit-article-main-author">Hauptautor/in (Korrespondenz)</label>
                            <input type="text" id="zk-edit-article-main-author" name="hauptautorin">
                        </div>
                    </div>

                    <!-- Inhalt -->
                    <div class="zk-form-section">
                        <h4 class="zk-form-section-title">Inhalt</h4>

                        <div class="zk-form-group">
                            <label for="zk-edit-article-content">Artikeltext</label>
                            <textarea id="zk-edit-article-content" name="content" rows="10" class="zk-html-editor"></textarea>
                        </div>

                        <div class="zk-form-group">
                            <label for="zk-edit-article-literatur">Literaturverzeichnis</label>
                            <textarea id="zk-edit-article-literatur" name="literatur" rows="4"></textarea>
                        </div>
                    </div>

                    <!-- Abstracts & Keywords -->
                    <div class="zk-form-section">
                        <h4 class="zk-form-section-title">Abstracts & Keywords</h4>

                        <div class="zk-form-row">
                            <div class="zk-form-group">
                                <label for="zk-edit-article-abstract">Zusammenfassung (Deutsch)</label>
                                <textarea id="zk-edit-article-abstract" name="abstract_deutsch" rows="4"></textarea>
                            </div>
                            <div class="zk-form-group">
                                <label for="zk-edit-article-abstract-en">Abstract (English)</label>
                                <textarea id="zk-edit-article-abstract-en" name="abstract" rows="4"></textarea>
                            </div>
                        </div>

                        <div class="zk-form-row">
                            <div class="zk-form-group">
                                <label for="zk-edit-article-keywords">Keywords (Deutsch)</label>
                                <input type="text" id="zk-edit-article-keywords" name="keywords_deutsch">
                            </div>
                            <div class="zk-form-group">
                                <label for="zk-edit-article-keywords-en">Keywords (English)</label>
                                <input type="text" id="zk-edit-article-keywords-en" name="keywords_englisch">
                            </div>
                        </div>
                    </div>

                    <!-- Optionen -->
                    <div class="zk-form-section">
                        <h4 class="zk-form-section-title">Optionen</h4>

                        <div class="zk-form-group">
                            <label class="zk-checkbox-label">
                                <input type="checkbox" id="zk-edit-article-fulltext" name="volltext_anzeigen" value="1">
                                Volltext anzeigen
                            </label>
                        </div>
                    </div>

                    <!-- Verknüpfung mit Ausgabe -->
                    <div class="zk-form-section">
                        <h4 class="zk-form-section-title">Verknüpfung</h4>
                        <div class="zk-form-group zk-article-link-section">
                            <label>Verknüpft mit Ausgabe</label>
                            <div id="zk-article-linked-issue" class="zk-linked-issue-display">
                                <span class="zk-no-link">Nicht verknüpft</span>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="zk-modal-footer">
                <button type="button" class="zk-btn zk-btn-danger" id="zk-delete-article" style="margin-right: auto;">
                    Löschen
                </button>
                <button type="button" class="zk-btn zk-btn-secondary zk-modal-cancel">Abbrechen</button>
                <button type="button" class="zk-btn zk-btn-primary" id="zk-save-edit-article">
                    Speichern
                </button>
            </div>
        </div>
    </div>

    <!-- Modal: Artikel verknüpfen -->
    <div class="zk-modal" id="zk-modal-link-article">
        <div class="zk-modal-overlay"></div>
        <div class="zk-modal-content">
            <div class="zk-modal-header">
                <h3>Artikel verknüpfen</h3>
                <button type="button" class="zk-modal-close">&times;</button>
            </div>
            <div class="zk-modal-body">
                <input type="hidden" id="zk-link-issue-id">
                <input type="hidden" id="zk-link-slot">

                <div class="zk-form-group">
                    <label for="zk-link-article-search">Artikel suchen</label>
                    <input type="text" id="zk-link-article-search" class="zk-input" placeholder="Titel oder Autor eingeben...">
                </div>

                <div class="zk-available-articles" id="zk-available-articles">
                    <div class="zk-loading">
                        <div class="zk-spinner"></div>
                        <span>Lade verfügbare Artikel...</span>
                    </div>
                </div>
            </div>
            <div class="zk-modal-footer">
                <button type="button" class="zk-btn zk-btn-secondary zk-modal-cancel">Abbrechen</button>
            </div>
        </div>
    </div>

    <!-- Toast Notifications -->
    <div class="zk-toast-container" id="zk-toast-container"></div>
</div>
