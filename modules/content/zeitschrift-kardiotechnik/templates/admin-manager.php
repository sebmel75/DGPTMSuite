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
        <button type="button" class="zk-tab" data-tab="accepted">
            Akzeptierte Artikel
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

    <!-- Tab: Akzeptierte Artikel -->
    <div class="zk-tab-content" id="zk-tab-accepted">
        <div class="zk-accepted-list" id="zk-accepted-list">
            <div class="zk-loading">
                <div class="zk-spinner"></div>
                <span>Lade Artikel...</span>
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

    <!-- Toast Notifications -->
    <div class="zk-toast-container" id="zk-toast-container"></div>
</div>
