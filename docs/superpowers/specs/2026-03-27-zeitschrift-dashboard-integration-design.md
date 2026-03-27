# Zeitschrift Verwaltung — Dashboard-Integration

**Datum:** 2026-03-27
**Modul:** zeitschrift-kardiotechnik (content) + mitglieder-dashboard (business)
**Ziel:** `[zeitschrift_verwaltung]` in 4 eigenstaendige Shortcodes aufsplitten, als Folder-Tabs ins Mitglieder-Dashboard einbetten, Design nach Forum-Vorbild harmonisieren.

---

## 1. Shortcode-Architektur

### 4 neue Shortcodes

| Shortcode | Template | Inhalt |
|-----------|----------|--------|
| `[zk_ausgaben]` | `templates/_partial-issues.php` | Filter-Leiste + Ausgaben-Liste |
| `[zk_artikel]` | `templates/_partial-articles.php` | Such-/Sortierleiste + Artikel-Liste + "Neuer Artikel"-Button |
| `[zk_einreichungen]` | `templates/_partial-accepted.php` | Akzeptierte Einreichungen-Liste |
| `[zk_pdfimport]` | `templates/_partial-pdf-import.php` | 3-Schritt-Import-Wizard |

Alle registriert in `includes/class-shortcodes.php` als Methoden von `ZK_Shortcodes`.

### Modal-Zuordnung

| Shortcode | Eigene Modals |
|-----------|---------------|
| `[zk_ausgaben]` | Neue Ausgabe (`zk-modal-new`), Ausgabe bearbeiten (`zk-modal-edit`), Artikel verknuepfen (`zk-modal-link-article`) |
| `[zk_artikel]` | Neuer Artikel (`zk-modal-new-article`), Artikel bearbeiten (`zk-modal-edit-article`) |
| `[zk_einreichungen]` | Keine |
| `[zk_pdfimport]` | KI-Einstellungen (`zk-modal-ai-settings`) |

### Toast-Container Deduplizierung

Statische Flag `ZK_Shortcodes::$shared_rendered = false`. Jeder Shortcode ruft `self::render_shared_elements()` auf, das den Toast-Container (`zk-toast-container`) nur beim ersten Aufruf ausgibt.

### Rueckwaertskompatibilitaet

`[zeitschrift_verwaltung]` bleibt erhalten. Rendert alle 4 Partials + Header + internes Tab-System wie bisher (fuer standalone-Nutzung ausserhalb des Dashboards).

### Berechtigungspruefung

Alle 4 Shortcodes pruefen `ZK_Shortcodes::user_can_manage()` — prueft `manage_options` Capability, `zeitschriftmanager` User-Meta oder `editor_in_chief` User-Meta.

---

## 2. Template-Aufsplittung

Aus `templates/admin-manager.php` werden Partials extrahiert:

```
templates/
  admin-manager.php              (bestehend, ruft Partials auf)
  _partial-issues.php            (Zeile 56-74 aus admin-manager)
  _partial-articles.php          (Zeile 77-105)
  _partial-accepted.php          (Zeile 108-115)
  _partial-pdf-import.php        (Zeile 118-259)
  _partial-modals-issues.php     (Modals: new, edit, link-article)
  _partial-modals-articles.php   (Modals: new-article, edit-article)
  _partial-modals-pdf.php        (Modal: ai-settings)
```

`admin-manager.php` wird refactored: inkludiert die Partials statt Inline-HTML. Keine Verhaltensaenderung.

---

## 3. JS-Initialisierung & Lazy Loading

### Aenderungen in `assets/js/admin.js`

**`ZKManager.init()` neu:**

```
init()
  +-- config = zkAdmin
  +-- isDashboard = !!$('#zk-manager').closest('.dgptm-dash').length
  |                 ODER !!$('.dgptm-dash .zk-section-wrap').length
  +-- bindEvents()           (wie bisher, document.on delegation)
  +-- bindDashboardHooks()   (NEU, nur im Dashboard-Modus)
  +-- autoDetect()           (NEU, laedt sichtbare Sections sofort)
```

**Standalone-Modus** (kein `.dgptm-dash` Elternelement): Verhalten wie bisher — `loadIssues()` + `loadYearFilter()` sofort.

**Dashboard-Modus:** Lauscht auf `dgptm:ftab-switched` Custom-Event.

### Dashboard-Event (in `dashboard.js`)

Neues Custom-Event beim Folder-Tab-Wechsel:

```js
$(document).trigger('dgptm:ftab-switched', { panel: fpanelId });
```

Wird am Ende des bestehenden Folder-Tab-Click-Handlers gefeuert.

### Lazy-Load-Mapping

| Panel enthaelt | Triggert |
|---------------|----------|
| `#zk-issues-list` | `loadYearFilter()` + `loadIssues()` |
| `#zk-articles-list` | `loadArticles()` |
| `#zk-accepted-list` | `loadAcceptedArticles()` |
| PDF-Import Wizard | nichts (statisch bis User-Interaktion) |

Jede Load-Funktion wird mit einem `loaded`-Flag versehen, damit sie nur einmal automatisch aufgerufen wird (manuelle Refreshes weiterhin moeglich).

### autoDetect()

Prueft beim Init, welche Sections bereits sichtbar sind (erster Folder-Tab) und laedt diese sofort. Deckt sowohl Dashboard als auch Standalone ab.

---

## 4. Cross-Tab-Navigation

### Problem

Im standalone-Modus nutzt der PDF-Import nach erfolgreichem Import `switchTab('issues')`. Im Dashboard muss stattdessen der Ausgaben-Folder-Tab aktiviert werden.

### Loesung: `navigateToSection(sectionId)`

```
navigateToSection('issues')
  +-- Dashboard-Kontext?
  |   +-- ja: findet .dgptm-ftab[data-ftab="zk-ausgaben"] -> .click()
  |         -> Dashboard-JS handled Show/Hide + feuert dgptm:ftab-switched
  |         -> ZKManager reagiert auf Event -> loadIssues()
  +-- nein (standalone): switchTab('issues') wie bisher
```

### Tab-ID-Mapping

```js
sectionToTab: {
    'issues':   'zk-ausgaben',
    'articles': 'zk-artikel',
    'accepted': 'zk-einreichungen',
    'pdf-import': 'zk-pdfimport'
}
```

### Stellen die `navigateToSection` nutzen

- Nach erfolgreichem PDF-Import (Zeile ~1766): `switchTab('issues')` -> `navigateToSection('issues')`
- Nach Import verwerfen: gleiches Pattern

---

## 5. CSS-Harmonisierung (Forum-Vorbild)

### Design-Referenz: `dgptm-forum/assets/css/forum.css`

Filigran, minimal, kein Shadow, 4px Radius, 12px Buttons, `!important` gegen Theme-Overrides.

### Scoping

Alle Overrides unter `.dgptm-dash .zk-*` am Ende von `assets/css/admin.css`.

### Entfaellt im Dashboard-Kontext

- `zk-manager-header` (Titel + "Angemeldet als") — `display: none`
- `zk-manager-tabs` — `display: none` (durch Dashboard Folder-Tabs ersetzt)
- `box-shadow` auf allen Elementen
- `border-radius: 12px` -> `4px`
- `transform: translateY` Hover-Effekte
- CSS Custom Properties (`--zk-*`)

### Overrides nach Forum-Pattern

| Element | Standalone | Dashboard-Kontext |
|---------|-----------|-------------------|
| `.zk-btn` | eigenes Design | `4px 10px`, `12px`, `border-radius: 4px`, `#0073aa`, `!important` |
| `.zk-btn.zk-btn-primary` | gross | wie `.dgptm-forum-btn` |
| `.zk-btn-small` | - | `2px 8px`, `11px` wie `.dgptm-forum-btn-sm` |
| `.zk-btn.zk-btn-danger` | - | `#dc3232` wie `.dgptm-forum-btn.danger` |
| `.zk-input`, `.zk-select` | eigenes Design | `8px` padding, `1px solid #ccc`, `4px` radius |
| Labels | - | `font-weight: 500`, `margin-bottom: 4px` |
| Listen-Items | `20px 24px` padding, shadow | `12px 15px`, `border-bottom: 1px solid #eee`, kein shadow |
| Section-Headers | gross | `font-size: 0.9em`, `margin-bottom: 1em` |
| Loading | eigener Spinner | `text-align: center; padding: 30px; color: #888` |
| Status-Badges | - | `2px 8px`, `border-radius: 10px`, `0.75em`, `font-weight: 600` |
| Filter-Leiste | `margin: 20px` | `margin-bottom: 12px`, `gap: 8px` |
| Abstände allgemein | 20-24px | 12-15px |

### Farben im Dashboard-Kontext

- Primaer-Aktionen: `#0073aa` (WP-Blau, wie Forum)
- Text: `#1d2327`
- Meta/Muted: `#888`
- Borders: `#eee` (Listen), `#ccc` (Inputs)
- Danger: `#dc3232`
- Hover-Background: `#f8f9fa`

### Modals

Behalten eigenstaendiges Design (Overlay, zentriert). Nur Buttons/Inputs darin werden filigraner (gleiche Overrides).

### Geschaetzter Umfang

Ca. 80-100 Zeilen CSS-Overrides im `.dgptm-dash`-Scope.

---

## 6. Dashboard-Tab-Konfiguration

Wird ueber "Dashboard Config" im WP-Admin eingerichtet (nicht hartcodiert).

### Haupt-Tab

| Feld | Wert |
|------|------|
| id | `zeitschrift` |
| label | `Zeitschrift` |
| parent | _(leer)_ |
| permission | `acf:zeitschriftmanager` |
| content | _(leer)_ |
| order | `80` |
| active | `true` |

### Sub-Tabs

| id | label | parent | order | permission | content |
|----|-------|--------|-------|------------|---------|
| `zk-ausgaben` | Ausgaben | `zeitschrift` | 81 | `always` | `[zk_ausgaben]` |
| `zk-artikel` | Artikel | `zeitschrift` | 82 | `always` | `[zk_artikel]` |
| `zk-einreichungen` | Einreichungen | `zeitschrift` | 83 | `always` | `[zk_einreichungen]` |
| `zk-pdfimport` | PDF-Import | `zeitschrift` | 84 | `always` | `[zk_pdfimport]` |

Sub-Tabs nutzen `permission: always` — der Haupt-Tab `acf:zeitschriftmanager` filtert bereits.

---

## 7. Dateien die geaendert werden

| Datei | Aenderung |
|-------|-----------|
| `zeitschrift-kardiotechnik/includes/class-shortcodes.php` | 4 neue Shortcode-Methoden, `render_shared_elements()`, statische Flag |
| `zeitschrift-kardiotechnik/templates/admin-manager.php` | Refactored: inkludiert Partials |
| `zeitschrift-kardiotechnik/templates/_partial-issues.php` | NEU |
| `zeitschrift-kardiotechnik/templates/_partial-articles.php` | NEU |
| `zeitschrift-kardiotechnik/templates/_partial-accepted.php` | NEU |
| `zeitschrift-kardiotechnik/templates/_partial-pdf-import.php` | NEU |
| `zeitschrift-kardiotechnik/templates/_partial-modals-issues.php` | NEU |
| `zeitschrift-kardiotechnik/templates/_partial-modals-articles.php` | NEU |
| `zeitschrift-kardiotechnik/templates/_partial-modals-pdf.php` | NEU |
| `zeitschrift-kardiotechnik/assets/js/admin.js` | Dashboard-Hooks, Lazy Loading, navigateToSection, autoDetect |
| `zeitschrift-kardiotechnik/assets/css/admin.css` | +80-100 Zeilen `.dgptm-dash .zk-*` Overrides |
| `mitglieder-dashboard/assets/js/dashboard.js` | `dgptm:ftab-switched` Event feuern |

Dashboard-Tabs werden ueber WP-Admin "Dashboard Config" konfiguriert, keine Code-Aenderung am Dashboard-Modul noetig (ausser dem JS-Event).
