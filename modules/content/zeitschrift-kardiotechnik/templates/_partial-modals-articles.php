<?php
/**
 * Partial: Modals für Artikel
 * Enthält 2 Modals: zk-modal-new-article (Neuen Artikel erstellen),
 * zk-modal-edit-article (Artikel bearbeiten).
 *
 * @package Zeitschrift_Kardiotechnik
 */

if (!defined('ABSPATH')) exit;
?>

<div class="zk-section-wrap" data-section="modals-articles">

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

</div>
