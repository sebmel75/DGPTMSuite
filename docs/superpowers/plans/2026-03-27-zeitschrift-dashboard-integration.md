# Zeitschrift Dashboard-Integration — Implementierungsplan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `[zeitschrift_verwaltung]` in 4 eigenstaendige Shortcodes aufsplitten, als Folder-Tabs ins Mitglieder-Dashboard einbetten, CSS nach Forum-Vorbild harmonisieren.

**Architecture:** Template-Partials aus admin-manager.php extrahieren, 4 neue Shortcodes in class-shortcodes.php registrieren, ZKManager.init() um Dashboard-Modus erweitern, CSS-Override-Layer fuer `.dgptm-dash`-Kontext.

**Tech Stack:** WordPress/PHP 7.4+, jQuery, ACF, AJAX

**Spec:** `docs/superpowers/specs/2026-03-27-zeitschrift-dashboard-integration-design.md`

---

## File Structure

### New Files (8 template partials)
- `modules/content/zeitschrift-kardiotechnik/templates/_partial-issues.php` — Ausgaben: Filter + Liste
- `modules/content/zeitschrift-kardiotechnik/templates/_partial-articles.php` — Artikel: Suche/Sort + Liste + Button
- `modules/content/zeitschrift-kardiotechnik/templates/_partial-accepted.php` — Akzeptierte Einreichungen
- `modules/content/zeitschrift-kardiotechnik/templates/_partial-pdf-import.php` — 3-Schritt-Wizard
- `modules/content/zeitschrift-kardiotechnik/templates/_partial-modals-issues.php` — Modals: Neue Ausgabe, Bearbeiten, Verknuepfen
- `modules/content/zeitschrift-kardiotechnik/templates/_partial-modals-articles.php` — Modals: Neuer Artikel, Bearbeiten
- `modules/content/zeitschrift-kardiotechnik/templates/_partial-modals-pdf.php` — Modal: KI-Einstellungen

### Modified Files (5)
- `modules/content/zeitschrift-kardiotechnik/includes/class-shortcodes.php` — 4 neue Shortcodes + shared elements
- `modules/content/zeitschrift-kardiotechnik/templates/admin-manager.php` — Refactor auf Partials
- `modules/content/zeitschrift-kardiotechnik/assets/js/admin.js` — Dashboard-Modus + Lazy Loading
- `modules/content/zeitschrift-kardiotechnik/assets/css/admin.css` — Forum-Style Overrides
- `modules/business/mitglieder-dashboard/assets/js/dashboard.js` — ftab-switched Event

---

## Task 1: Template-Partials extrahieren

**Files:**
- Create: `modules/content/zeitschrift-kardiotechnik/templates/_partial-issues.php`
- Create: `modules/content/zeitschrift-kardiotechnik/templates/_partial-articles.php`
- Create: `modules/content/zeitschrift-kardiotechnik/templates/_partial-accepted.php`
- Create: `modules/content/zeitschrift-kardiotechnik/templates/_partial-pdf-import.php`
- Create: `modules/content/zeitschrift-kardiotechnik/templates/_partial-modals-issues.php`
- Create: `modules/content/zeitschrift-kardiotechnik/templates/_partial-modals-articles.php`
- Create: `modules/content/zeitschrift-kardiotechnik/templates/_partial-modals-pdf.php`

Jedes Partial wird aus dem bestehenden `admin-manager.php` extrahiert. Die Inhalte kommen 1:1 aus dem Original — kein neuer Code.

- [ ] **Step 1: _partial-issues.php erstellen**

Zeile 56-74 aus `admin-manager.php` — der Ausgaben-Tab-Content (ohne das umschliessende `<div class="zk-tab-content">` Tag):

```php
<?php
/**
 * Partial: Ausgaben-Verwaltung
 * Wird von [zk_ausgaben] und [zeitschrift_verwaltung] eingebunden
 */
if (!defined('ABSPATH')) exit;
?>
<div class="zk-section-wrap" data-section="issues">
    <div class="zk-issues-filters">
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
```

Hinweis: Die Buttons "Neue Ausgabe" und "Aktualisieren" wandern aus dem Header (`zk-manager-actions`) in die Filter-Leiste, da der Header im Dashboard-Kontext entfaellt. Das erfordert eine kleine Layout-Anpassung — die Filter-Leiste bekommt `display: flex; flex-wrap: wrap; gap: 8px; align-items: center;`.

- [ ] **Step 2: _partial-articles.php erstellen**

Zeile 77-105 aus `admin-manager.php`:

```php
<?php
/**
 * Partial: Artikel-Verwaltung
 * Wird von [zk_artikel] und [zeitschrift_verwaltung] eingebunden
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
```

- [ ] **Step 3: _partial-accepted.php erstellen**

Zeile 108-115 aus `admin-manager.php`:

```php
<?php
/**
 * Partial: Akzeptierte Einreichungen
 * Wird von [zk_einreichungen] und [zeitschrift_verwaltung] eingebunden
 */
if (!defined('ABSPATH')) exit;
?>
<div class="zk-section-wrap" data-section="accepted">
    <div class="zk-accepted-list" id="zk-accepted-list">
        <div class="zk-loading">
            <div class="zk-spinner"></div>
            <span>Lade Einreichungen...</span>
        </div>
    </div>
</div>
```

- [ ] **Step 4: _partial-pdf-import.php erstellen**

Zeile 118-259 aus `admin-manager.php` — der gesamte PDF-Import-Wizard:

```php
<?php
/**
 * Partial: PDF-Import Wizard
 * Wird von [zk_pdfimport] und [zeitschrift_verwaltung] eingebunden
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

        <!-- Schritt 2: KI-Extraktion -->
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

        <!-- Schritt 3: Pruefen & Importieren -->
        <div class="zk-import-step" id="zk-import-step-3">
            <div class="zk-step-header">
                <span class="zk-step-number">3</span>
                <h3>Prüfen &amp; Importieren</h3>
            </div>
            <div class="zk-step-content">
                <div class="zk-import-preview" style="display: none;">
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
                    <div class="zk-preview-section">
                        <h4>Erkannte Artikel (<span id="zk-article-count">0</span>)</h4>
                        <div class="zk-articles-preview-list" id="zk-articles-preview"></div>
                    </div>
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
```

- [ ] **Step 5: _partial-modals-issues.php erstellen**

Zeile 302-401 + 694-721 aus `admin-manager.php` — Modals fuer Neue Ausgabe, Bearbeiten, Artikel verknuepfen:

```php
<?php
/**
 * Partial: Modals fuer Ausgaben-Verwaltung
 * Wird von [zk_ausgaben] und [zeitschrift_verwaltung] eingebunden
 */
if (!defined('ABSPATH')) exit;
?>
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
                        <div class="zk-linked-articles" id="zk-linked-articles"></div>
                    </div>
                </div>
            </form>
        </div>
        <div class="zk-modal-footer">
            <button type="button" class="zk-btn zk-btn-secondary zk-modal-cancel">Abbrechen</button>
            <button type="button" class="zk-btn zk-btn-primary" id="zk-save-edit-issue">Speichern</button>
        </div>
    </div>
</div>

<!-- Modal: Artikel verknuepfen -->
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
```

- [ ] **Step 6: _partial-modals-articles.php erstellen**

Zeile 404-691 aus `admin-manager.php` — Modals fuer Neuer Artikel + Artikel bearbeiten. Grosser Block — 1:1 aus dem Original. Komplett mit allen Form-Sections (Grunddaten, Autoren, Inhalt, Abstracts, Optionen, Verknuepfung).

Die Datei enthaelt die beiden Modals `zk-modal-new-article` und `zk-modal-edit-article` — den gesamten HTML-Block aus dem Original kopieren. Zu lang fuer den Plan, aber 1:1 aus admin-manager.php Zeile 404-691.

- [ ] **Step 7: _partial-modals-pdf.php erstellen**

Zeile 262-300 aus `admin-manager.php`:

```php
<?php
/**
 * Partial: Modal KI-Einstellungen
 * Wird von [zk_pdfimport] und [zeitschrift_verwaltung] eingebunden
 */
if (!defined('ABSPATH')) exit;
?>
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
                        <option value="claude-opus-4-5-20251101">Claude Opus 4.5</option>
                        <option value="claude-3-5-sonnet-20241022">Claude 3.5 Sonnet</option>
                        <option value="claude-3-5-haiku-20241022">Claude 3.5 Haiku (schnell)</option>
                        <option value="gpt-4o">GPT-4o</option>
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
```

- [ ] **Step 8: Commit**

```bash
git add modules/content/zeitschrift-kardiotechnik/templates/_partial-*.php
git commit -m "refactor(zeitschrift): Template-Partials aus admin-manager extrahieren"
```

---

## Task 2: admin-manager.php auf Partials umbauen

**Files:**
- Modify: `modules/content/zeitschrift-kardiotechnik/templates/admin-manager.php`

Das bestehende Template wird refactored: der Inline-HTML-Code wird durch `include` der Partials ersetzt. Keine Verhaltensaenderung — der `[zeitschrift_verwaltung]`-Shortcode rendert weiterhin alles.

- [ ] **Step 1: admin-manager.php ersetzen**

```php
<?php
/**
 * Template: Zeitschrift Verwaltung (Frontend)
 * Komplett AJAX-basiert fuer Benutzer ohne Backend-Zugriff
 *
 * Dieses Template wird von [zeitschrift_verwaltung] genutzt (standalone).
 * Die einzelnen Sektionen sind als Partials verfuegbar fuer Dashboard-Integration.
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
        <button type="button" class="zk-tab zk-tab-active" data-tab="issues">Ausgaben</button>
        <button type="button" class="zk-tab" data-tab="articles">Artikel</button>
        <button type="button" class="zk-tab" data-tab="accepted">Akzeptierte Einreichungen</button>
        <button type="button" class="zk-tab" data-tab="pdf-import">PDF-Import</button>
    </div>

    <!-- Tab-Contents -->
    <div class="zk-tab-content zk-tab-active" id="zk-tab-issues">
        <?php include ZK_PLUGIN_DIR . 'templates/_partial-issues.php'; ?>
    </div>

    <div class="zk-tab-content" id="zk-tab-articles">
        <?php include ZK_PLUGIN_DIR . 'templates/_partial-articles.php'; ?>
    </div>

    <div class="zk-tab-content" id="zk-tab-accepted">
        <?php include ZK_PLUGIN_DIR . 'templates/_partial-accepted.php'; ?>
    </div>

    <div class="zk-tab-content" id="zk-tab-pdf-import">
        <?php include ZK_PLUGIN_DIR . 'templates/_partial-pdf-import.php'; ?>
    </div>

    <!-- Alle Modals -->
    <?php include ZK_PLUGIN_DIR . 'templates/_partial-modals-issues.php'; ?>
    <?php include ZK_PLUGIN_DIR . 'templates/_partial-modals-articles.php'; ?>
    <?php include ZK_PLUGIN_DIR . 'templates/_partial-modals-pdf.php'; ?>

    <!-- Toast Notifications -->
    <div class="zk-toast-container" id="zk-toast-container"></div>
</div>
```

- [ ] **Step 2: Verifizieren**

Standalone-Shortcode `[zeitschrift_verwaltung]` auf einer Testseite einbinden. Pruefen:
- Alle 4 Tabs sichtbar und funktional
- Modals oeffnen/schliessen
- Ausgaben laden, Artikel laden

- [ ] **Step 3: Commit**

```bash
git add modules/content/zeitschrift-kardiotechnik/templates/admin-manager.php
git commit -m "refactor(zeitschrift): admin-manager.php nutzt jetzt Partials"
```

---

## Task 3: Neue Shortcodes in class-shortcodes.php

**Files:**
- Modify: `modules/content/zeitschrift-kardiotechnik/includes/class-shortcodes.php`

4 neue Shortcode-Methoden + statische Flag fuer Toast-Deduplizierung.

- [ ] **Step 1: Statische Property und Shortcode-Registrierung hinzufuegen**

In `class-shortcodes.php`, nach Zeile 16 (`private static $instance = null;`), neue Property:

```php
private static $shared_rendered = false;
```

Im Konstruktor (nach Zeile 29 `add_shortcode('zeitschrift_aktuell'...)`), 4 neue Shortcodes registrieren:

```php
add_shortcode('zk_ausgaben', [$this, 'render_issues_section']);
add_shortcode('zk_artikel', [$this, 'render_articles_section']);
add_shortcode('zk_einreichungen', [$this, 'render_accepted_section']);
add_shortcode('zk_pdfimport', [$this, 'render_pdfimport_section']);
```

- [ ] **Step 2: Shared-Elements-Methode hinzufuegen**

Nach der `render_current()`-Methode (nach Zeile 249), neue Methode:

```php
/**
 * Rendert Toast-Container (nur einmal pro Seite)
 */
private static function render_shared_elements() {
    if (self::$shared_rendered) return '';
    self::$shared_rendered = true;
    return '<div class="zk-toast-container" id="zk-toast-container"></div>';
}
```

- [ ] **Step 3: render_issues_section() hinzufuegen**

```php
/**
 * Shortcode: [zk_ausgaben]
 * Ausgaben-Verwaltung als eigenstaendiger Shortcode fuer Dashboard-Integration
 */
public function render_issues_section($atts) {
    if (!self::user_can_manage()) {
        return '<p class="zk-error">Sie haben keine Berechtigung für diesen Bereich.</p>';
    }

    wp_enqueue_style('zk-admin');
    wp_enqueue_script('zk-admin');
    wp_enqueue_editor();
    wp_localize_script('zk-admin', 'zkAdmin', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'adminUrl' => admin_url(),
        'nonce' => wp_create_nonce('zk_admin_nonce')
    ]);

    ob_start();
    include ZK_PLUGIN_DIR . 'templates/_partial-issues.php';
    include ZK_PLUGIN_DIR . 'templates/_partial-modals-issues.php';
    echo self::render_shared_elements();
    return ob_get_clean();
}
```

- [ ] **Step 4: render_articles_section() hinzufuegen**

```php
/**
 * Shortcode: [zk_artikel]
 * Artikel-Verwaltung als eigenstaendiger Shortcode fuer Dashboard-Integration
 */
public function render_articles_section($atts) {
    if (!self::user_can_manage()) {
        return '<p class="zk-error">Sie haben keine Berechtigung für diesen Bereich.</p>';
    }

    wp_enqueue_style('zk-admin');
    wp_enqueue_script('zk-admin');
    wp_enqueue_editor();
    wp_localize_script('zk-admin', 'zkAdmin', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'adminUrl' => admin_url(),
        'nonce' => wp_create_nonce('zk_admin_nonce')
    ]);

    ob_start();
    include ZK_PLUGIN_DIR . 'templates/_partial-articles.php';
    include ZK_PLUGIN_DIR . 'templates/_partial-modals-articles.php';
    echo self::render_shared_elements();
    return ob_get_clean();
}
```

- [ ] **Step 5: render_accepted_section() hinzufuegen**

```php
/**
 * Shortcode: [zk_einreichungen]
 * Akzeptierte Einreichungen als eigenstaendiger Shortcode fuer Dashboard-Integration
 */
public function render_accepted_section($atts) {
    if (!self::user_can_manage()) {
        return '<p class="zk-error">Sie haben keine Berechtigung für diesen Bereich.</p>';
    }

    wp_enqueue_style('zk-admin');
    wp_enqueue_script('zk-admin');
    wp_localize_script('zk-admin', 'zkAdmin', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'adminUrl' => admin_url(),
        'nonce' => wp_create_nonce('zk_admin_nonce')
    ]);

    ob_start();
    include ZK_PLUGIN_DIR . 'templates/_partial-accepted.php';
    echo self::render_shared_elements();
    return ob_get_clean();
}
```

- [ ] **Step 6: render_pdfimport_section() hinzufuegen**

```php
/**
 * Shortcode: [zk_pdfimport]
 * PDF-Import Wizard als eigenstaendiger Shortcode fuer Dashboard-Integration
 */
public function render_pdfimport_section($atts) {
    if (!self::user_can_manage()) {
        return '<p class="zk-error">Sie haben keine Berechtigung für diesen Bereich.</p>';
    }

    wp_enqueue_style('zk-admin');
    wp_enqueue_script('zk-admin');
    wp_enqueue_editor();
    wp_localize_script('zk-admin', 'zkAdmin', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'adminUrl' => admin_url(),
        'nonce' => wp_create_nonce('zk_admin_nonce')
    ]);

    ob_start();
    include ZK_PLUGIN_DIR . 'templates/_partial-pdf-import.php';
    include ZK_PLUGIN_DIR . 'templates/_partial-modals-pdf.php';
    echo self::render_shared_elements();
    return ob_get_clean();
}
```

- [ ] **Step 7: Commit**

```bash
git add modules/content/zeitschrift-kardiotechnik/includes/class-shortcodes.php
git commit -m "feat(zeitschrift): 4 neue Shortcodes fuer Dashboard-Integration"
```

---

## Task 4: Dashboard-JS Event feuern

**Files:**
- Modify: `modules/business/mitglieder-dashboard/assets/js/dashboard.js:35-48`

Ein `dgptm:ftab-switched` Event muss gefeuert werden, wenn ein Folder-Tab gewechselt wird. Ebenso beim Main-Tab-Switch ein `dgptm:tab-switched`.

- [ ] **Step 1: ftab-switched Event nach Folder-Tab-Click**

In `dashboard.js`, Zeile 35-48, nach dem `if (!loaded[id])` Block (Zeile 47), vor der schliessenden `});` des Click-Handlers, folgende Zeile einfuegen:

Aktueller Code (Zeile 35-48):
```js
$d.on('click', '.dgptm-ftab[data-ftab]', function(e) {
    e.preventDefault();
    var id = $(this).data('ftab');
    var $folder = $(this).closest('.dgptm-folder');
    $folder.find('.dgptm-ftab').removeClass('dgptm-ftab-active');
    $(this).addClass('dgptm-ftab-active');
    $folder.find('.dgptm-fpanel').hide();
    var $fp = $folder.find('[data-fpanel="' + id + '"]').show();

    if (!loaded[id]) {
        loadTab(id, $fp);
        loaded[id] = true;
    }
});
```

Neuer Code:
```js
$d.on('click', '.dgptm-ftab[data-ftab]', function(e) {
    e.preventDefault();
    var id = $(this).data('ftab');
    var $folder = $(this).closest('.dgptm-folder');
    $folder.find('.dgptm-ftab').removeClass('dgptm-ftab-active');
    $(this).addClass('dgptm-ftab-active');
    $folder.find('.dgptm-fpanel').hide();
    var $fp = $folder.find('[data-fpanel="' + id + '"]').show();

    if (!loaded[id]) {
        loadTab(id, $fp);
        loaded[id] = true;
    }

    $(document).trigger('dgptm:ftab-switched', { panel: id });
});
```

- [ ] **Step 2: Auch bei Tab-Load-Complete Event feuern**

Das `dgptm_tab_loaded` Event (Zeile 95) feuert bereits nach AJAX-Load. Zusaetzlich ein `dgptm:ftab-switched` feuern, damit der ZKManager auf nachgeladene Inhalte reagieren kann.

In der `loadTab`-Funktion (Zeile 78-102), nach Zeile 95 (`$(document).trigger('dgptm_tab_loaded', [id]);`):

```js
$(document).trigger('dgptm:ftab-switched', { panel: id });
```

- [ ] **Step 3: Commit**

```bash
git add modules/business/mitglieder-dashboard/assets/js/dashboard.js
git commit -m "feat(dashboard): dgptm:ftab-switched Event bei Folder-Tab-Wechsel"
```

---

## Task 5: ZKManager Dashboard-Modus in admin.js

**Files:**
- Modify: `modules/content/zeitschrift-kardiotechnik/assets/js/admin.js`

Die groesste JS-Aenderung: Dashboard-Erkennung, Lazy Loading, navigateToSection.

- [ ] **Step 1: config um Dashboard-Properties erweitern**

In `admin.js`, Zeile 10-14 (`config` Objekt), erweitern:

```js
config: {
    ajaxUrl: '',
    nonce: '',
    adminUrl: ''
},
isDashboard: false,
sectionLoaded: {},
sectionToTab: {
    'issues':     'zk-ausgaben',
    'articles':   'zk-artikel',
    'accepted':   'zk-einreichungen',
    'pdf-import': 'zk-pdfimport'
},
```

- [ ] **Step 2: init() Methode umbauen**

Aktuelles `init()` (Zeile 19-35):
```js
init: function() {
    console.log('ZK Manager: Initialisierung gestartet');
    if (typeof zkAdmin === 'undefined') {
        console.error('ZK Admin: Konfiguration nicht geladen');
        return;
    }
    console.log('ZK Manager: Konfiguration geladen', zkAdmin);
    this.config = zkAdmin;
    this.bindEvents();
    this.loadYearFilter();
    this.loadIssues();
    console.log('ZK Manager: Initialisierung abgeschlossen');
},
```

Neues `init()`:
```js
init: function() {
    if (typeof zkAdmin === 'undefined') {
        return;
    }

    this.config = zkAdmin;
    this.isDashboard = $('.dgptm-dash .zk-section-wrap').length > 0;
    this.bindEvents();

    if (this.isDashboard) {
        this.bindDashboardHooks();
        this.autoDetect();
    } else {
        this.loadYearFilter();
        this.loadIssues();
    }
},
```

- [ ] **Step 3: bindDashboardHooks() hinzufuegen**

Nach `bindEvents()` (nach Zeile 204), neue Methode:

```js
/**
 * Dashboard-Events binden (Lazy Loading bei Tab-Wechsel)
 */
bindDashboardHooks: function() {
    var self = this;

    $(document).on('dgptm:ftab-switched', function(e, data) {
        if (!data || !data.panel) return;

        var $panel = $('[data-fpanel="' + data.panel + '"]');
        if (!$panel.length) return;

        // Issues-Section erkennen
        if ($panel.find('#zk-issues-list').length && !self.sectionLoaded.issues) {
            self.sectionLoaded.issues = true;
            self.loadYearFilter();
            self.loadIssues();
        }

        // Articles-Section erkennen
        if ($panel.find('#zk-articles-list').length && !self.sectionLoaded.articles) {
            self.sectionLoaded.articles = true;
            self.loadArticles();
        }

        // Accepted-Section erkennen
        if ($panel.find('#zk-accepted-list').length && !self.sectionLoaded.accepted) {
            self.sectionLoaded.accepted = true;
            self.loadAcceptedArticles();
        }
    });
},
```

- [ ] **Step 4: autoDetect() hinzufuegen**

Nach `bindDashboardHooks()`:

```js
/**
 * Erkennt bereits sichtbare Sections und laedt deren Daten
 */
autoDetect: function() {
    var self = this;

    // Kurze Verzoegerung: Dashboard rendert evtl. noch
    setTimeout(function() {
        $('.zk-section-wrap:visible').each(function() {
            var section = $(this).data('section');
            if (section === 'issues' && !self.sectionLoaded.issues) {
                self.sectionLoaded.issues = true;
                self.loadYearFilter();
                self.loadIssues();
            }
            if (section === 'articles' && !self.sectionLoaded.articles) {
                self.sectionLoaded.articles = true;
                self.loadArticles();
            }
            if (section === 'accepted' && !self.sectionLoaded.accepted) {
                self.sectionLoaded.accepted = true;
                self.loadAcceptedArticles();
            }
        });
    }, 100);
},
```

- [ ] **Step 5: navigateToSection() hinzufuegen**

Nach `autoDetect()`:

```js
/**
 * Navigiert zu einer Section — im Dashboard-Modus via Folder-Tab, standalone via switchTab
 */
navigateToSection: function(sectionId) {
    if (!this.isDashboard) {
        this.switchTab(sectionId);
        return;
    }

    var tabId = this.sectionToTab[sectionId];
    if (!tabId) return;

    var $ftab = $('.dgptm-ftab[data-ftab="' + tabId + '"]');
    if ($ftab.length) {
        $ftab.trigger('click');
    }
},
```

- [ ] **Step 6: switchTab-Aufrufe durch navigateToSection ersetzen**

In `admin.js` Zeile 1766 (`self.switchTab('issues');` nach erfolgreichem Import):

Ersetze:
```js
self.switchTab('issues');
self.loadIssues();
```
durch:
```js
self.navigateToSection('issues');
if (!self.isDashboard) self.loadIssues();
```

(Im Dashboard-Modus triggert `navigateToSection` den Tab-Click, der das `dgptm:ftab-switched`-Event feuert, das dann `loadIssues()` aufruft — sofern `sectionLoaded.issues` noch false ist. Falls bereits geladen, muss explizit nachgeladen werden. Besser so:)

```js
self.sectionLoaded.issues = false;
self.navigateToSection('issues');
if (!self.isDashboard) self.loadIssues();
```

- [ ] **Step 7: Init-Trigger anpassen**

Am Ende der Datei, Zeile 1946-1951:

Aktuell:
```js
$(document).ready(function() {
    if ($('#zk-manager').length > 0) {
        ZKManager.init();
        ZKManager.bindPdfImportEvents();
    }
});
```

Neu — auch triggern wenn `.zk-section-wrap` vorhanden (Dashboard-Modus):
```js
$(document).ready(function() {
    if ($('#zk-manager').length > 0 || $('.zk-section-wrap').length > 0) {
        ZKManager.init();
        ZKManager.bindPdfImportEvents();
    }
});
```

- [ ] **Step 8: Commit**

```bash
git add modules/content/zeitschrift-kardiotechnik/assets/js/admin.js
git commit -m "feat(zeitschrift): Dashboard-Modus mit Lazy Loading und Cross-Tab-Navigation"
```

---

## Task 6: CSS Forum-Style Overrides

**Files:**
- Modify: `modules/content/zeitschrift-kardiotechnik/assets/css/admin.css`

Am Ende der Datei (nach Zeile 1857) wird ein `.dgptm-dash`-Scoped Override-Block angefuegt.

- [ ] **Step 1: Dashboard-Override-Block anfuegen**

Am Ende von `admin.css` (nach dem letzten `}` auf Zeile 1857):

```css
/* ========================================
   Dashboard-Integration (Forum-Vorbild)
   Filigran, minimal, kein Shadow
   ======================================== */

/* Header und internes Tab-System ausblenden */
.dgptm-dash .zk-manager-header,
.dgptm-dash .zk-manager-tabs { display: none !important; }

/* Container-Reset */
.dgptm-dash .zk-manager,
.dgptm-dash .zk-section-wrap { max-width: 100%; padding: 0; }

/* Buttons: Forum-Stil */
.dgptm-dash .zk-btn,
.dgptm-dash button.zk-btn {
    padding: 4px 10px !important;
    border: 1px solid #0073aa !important;
    border-radius: 4px !important;
    background: #0073aa !important;
    color: #fff !important;
    font-size: 12px !important;
    font-weight: 400 !important;
    line-height: 1.4 !important;
    text-decoration: none !important;
    min-height: 0 !important;
    height: auto !important;
    box-shadow: none !important;
    transition: background .15s, box-shadow .15s;
}
.dgptm-dash .zk-btn:hover { background: #005d8c !important; box-shadow: 0 1px 3px rgba(0,0,0,.15); }

.dgptm-dash .zk-btn-secondary,
.dgptm-dash button.zk-btn-secondary {
    background: #f0f0f0 !important;
    border-color: #ccc !important;
    color: #555 !important;
}
.dgptm-dash .zk-btn-secondary:hover { background: #e0e0e0 !important; }

.dgptm-dash .zk-btn-danger,
.dgptm-dash button.zk-btn-danger { background: #dc3232 !important; border-color: #dc3232 !important; }
.dgptm-dash .zk-btn-danger:hover { background: #b02828 !important; }

.dgptm-dash .zk-btn-success,
.dgptm-dash button.zk-btn-success { background: #46b450 !important; border-color: #46b450 !important; }
.dgptm-dash .zk-btn-success:hover { background: #389e3e !important; }

.dgptm-dash .zk-btn-small,
.dgptm-dash .zk-btn-mini {
    padding: 2px 8px !important;
    font-size: 11px !important;
}

/* Inputs: Forum-Stil */
.dgptm-dash .zk-input,
.dgptm-dash .zk-select,
.dgptm-dash .zk-manager input[type="text"],
.dgptm-dash .zk-manager input[type="number"],
.dgptm-dash .zk-manager input[type="date"],
.dgptm-dash .zk-manager input[type="password"],
.dgptm-dash .zk-manager textarea,
.dgptm-dash .zk-manager select,
.dgptm-dash .zk-section-wrap input[type="text"],
.dgptm-dash .zk-section-wrap input[type="number"],
.dgptm-dash .zk-section-wrap input[type="date"],
.dgptm-dash .zk-section-wrap input[type="password"],
.dgptm-dash .zk-section-wrap textarea,
.dgptm-dash .zk-section-wrap select {
    padding: 8px !important;
    border: 1px solid #ccc !important;
    border-radius: 4px !important;
    font-size: 14px !important;
    box-shadow: none !important;
}

/* Labels */
.dgptm-dash .zk-form-group label {
    font-weight: 500;
    margin-bottom: 4px;
    font-size: 13px;
}

/* Listen: Forum-Stil */
.dgptm-dash .zk-issue-item,
.dgptm-dash .zk-article-item {
    padding: 12px 15px !important;
    border-bottom: 1px solid #eee !important;
    border: none !important;
    border-bottom: 1px solid #eee !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    background: none !important;
    transition: background 0.15s;
}
.dgptm-dash .zk-issue-item:hover,
.dgptm-dash .zk-article-item:hover { background: #f8f9fa !important; }

/* Text-Farben */
.dgptm-dash .zk-issue-title,
.dgptm-dash .zk-article-title-text { font-weight: 600; color: #1d2327; }
.dgptm-dash .zk-issue-meta,
.dgptm-dash .zk-article-meta { font-size: 0.85em; color: #888; }

/* Status-Badges */
.dgptm-dash .zk-status-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.75em;
    font-weight: 600;
}
.dgptm-dash .zk-status-online { background: #e7f5e7; color: #2e7d32; }
.dgptm-dash .zk-status-scheduled { background: #fff3e0; color: #e65100; }

/* Filter-Leiste kompakter */
.dgptm-dash .zk-issues-filters,
.dgptm-dash .zk-articles-header {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
    margin-bottom: 12px;
}

.dgptm-dash .zk-articles-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
    flex: 1;
}

/* Loading */
.dgptm-dash .zk-loading {
    text-align: center;
    padding: 30px;
    color: #888;
}

/* Section-Headers */
.dgptm-dash .zk-form-section-title {
    font-size: 0.9em;
    margin-bottom: 1em;
    color: #1d2327;
}

/* Modals: etwas filigraner */
.dgptm-dash .zk-modal-header h3 { font-size: 15px; }
.dgptm-dash .zk-modal-body { padding: 16px; }
.dgptm-dash .zk-modal-footer { padding: 12px 16px; }

/* Upload-Zone kompakter */
.dgptm-dash .zk-upload-zone { padding: 24px; }
.dgptm-dash .zk-upload-zone svg { width: 36px; height: 36px; }
.dgptm-dash .zk-upload-text { font-size: 13px; }

/* Responsive */
@media (max-width: 768px) {
    .dgptm-dash .zk-issues-filters,
    .dgptm-dash .zk-articles-header { flex-direction: column; align-items: stretch; }
}
```

- [ ] **Step 2: Commit**

```bash
git add modules/content/zeitschrift-kardiotechnik/assets/css/admin.css
git commit -m "style(zeitschrift): Dashboard-Overrides nach Forum-Vorbild"
```

---

## Task 7: Dashboard-Tabs konfigurieren (manuell)

**Files:** Keine Code-Aenderungen — Konfiguration ueber WP-Admin.

Diese Schritte werden manuell im WordPress-Admin ausgefuehrt, nicht per Code.

- [ ] **Step 1: Dashboard Config oeffnen**

WP-Admin → DGPTM Suite → Dashboard Config

- [ ] **Step 2: Haupt-Tab "Zeitschrift" anlegen**

| Feld | Wert |
|------|------|
| ID | `zeitschrift` |
| Label | `Zeitschrift` |
| Parent | _(leer)_ |
| Permission | `acf:zeitschriftmanager` |
| Content | _(leer lassen)_ |
| Order | `80` |
| Active | Ja |

- [ ] **Step 3: Sub-Tab "Ausgaben" anlegen**

| Feld | Wert |
|------|------|
| ID | `zk-ausgaben` |
| Label | `Ausgaben` |
| Parent | `zeitschrift` |
| Permission | `always` |
| Content | `[zk_ausgaben]` |
| Order | `81` |
| Active | Ja |

- [ ] **Step 4: Sub-Tab "Artikel" anlegen**

| Feld | Wert |
|------|------|
| ID | `zk-artikel` |
| Label | `Artikel` |
| Parent | `zeitschrift` |
| Permission | `always` |
| Content | `[zk_artikel]` |
| Order | `82` |
| Active | Ja |

- [ ] **Step 5: Sub-Tab "Einreichungen" anlegen**

| Feld | Wert |
|------|------|
| ID | `zk-einreichungen` |
| Label | `Einreichungen` |
| Parent | `zeitschrift` |
| Permission | `always` |
| Content | `[zk_einreichungen]` |
| Order | `83` |
| Active | Ja |

- [ ] **Step 6: Sub-Tab "PDF-Import" anlegen**

| Feld | Wert |
|------|------|
| ID | `zk-pdfimport` |
| Label | `PDF-Import` |
| Parent | `zeitschrift` |
| Permission | `always` |
| Content | `[zk_pdfimport]` |
| Order | `84` |
| Active | Ja |

- [ ] **Step 7: Speichern und testen**

Mitglieder-Dashboard im Frontend oeffnen (als User mit `zeitschriftmanager` User-Meta).
Pruefen: "Zeitschrift" Tab sichtbar mit 4 Folder-Tabs.

---

## Task 8: Integration testen

**Files:** Keine Aenderungen — Verifikation.

- [ ] **Step 1: Standalone-Modus pruefen**

Seite mit `[zeitschrift_verwaltung]` aufrufen. Pruefen:
- Header "Zeitschrift Verwaltung" sichtbar
- Alle 4 interne Tabs funktional
- Ausgaben laden, Artikel laden
- Neue Ausgabe erstellen (Modal oeffnet)
- PDF-Import Wizard funktional

- [ ] **Step 2: Dashboard-Modus pruefen**

Mitglieder-Dashboard oeffnen (als User mit `zeitschriftmanager`).
Pruefen:
- "Zeitschrift" Haupt-Tab sichtbar
- 4 Folder-Tabs: Ausgaben, Artikel, Einreichungen, PDF-Import
- Kein Header "Zeitschrift Verwaltung" sichtbar (ausgeblendet)
- Kein internes Tab-System sichtbar (ausgeblendet)

- [ ] **Step 3: Lazy Loading pruefen**

- Ausgaben-Tab (erster Folder-Tab): Daten laden sofort
- Artikel-Tab klicken: Daten laden erst beim Klick
- Einreichungen-Tab klicken: Daten laden erst beim Klick
- PDF-Import: Statisch, kein Auto-Load

- [ ] **Step 4: Cross-Tab-Navigation pruefen**

- PDF-Import durchfuehren (oder simulieren)
- Nach erfolgreichem Import: automatischer Wechsel zum Ausgaben-Folder-Tab

- [ ] **Step 5: Design pruefen**

- Buttons: Klein, `#0073aa`, 12px — wie Forum
- Listen: `border-bottom: 1px solid #eee`, kein Shadow
- Inputs: `1px solid #ccc`, `4px` radius
- Keine grossen Schatten oder abgerundeten Ecken
- Modals: Oeffnen korrekt ueber dem Dashboard

- [ ] **Step 6: Permission pruefen**

- Als User OHNE `zeitschriftmanager`: "Zeitschrift"-Tab nicht sichtbar
- Als Admin: Tab sichtbar (admin_bypass)
- Als User MIT `zeitschriftmanager`: Tab sichtbar

- [ ] **Step 7: Mobile pruefen**

- Dashboard Mobile-Dropdown: "Zeitschrift" erscheint
- Folder-Tabs scrollbar auf schmalem Bildschirm
- Buttons/Filter-Leiste stacken vertikal
