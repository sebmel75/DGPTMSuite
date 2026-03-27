<?php
/**
 * Partial: Modals für Ausgaben
 * Enthält 3 Modals: zk-modal-new (Neue Ausgabe), zk-modal-edit (Ausgabe bearbeiten),
 * zk-modal-link-article (Artikel verknüpfen).
 *
 * @package Zeitschrift_Kardiotechnik
 */

if (!defined('ABSPATH')) exit;
?>

<div class="zk-section-wrap" data-section="modals-issues">

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

</div>
