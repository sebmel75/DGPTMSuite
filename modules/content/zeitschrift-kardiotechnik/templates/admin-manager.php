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
