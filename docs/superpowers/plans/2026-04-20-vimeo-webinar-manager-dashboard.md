# Vimeo-Webinare Dashboard-Angleichung Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Modul `vimeo-webinare` auf v2.0.0 heben: Bisherigen Sammel-Shortcode `[vimeo_webinar_manager]` in drei klar getrennte Shortcodes aufteilen, Admin-Manager und neue Statistiken als Tabs ins Mitglieder-Dashboard integrieren, Frontend-Liste an Dashboard-Design angleichen.

**Architecture:** Repository-Pattern für Datenzugriffe (behebt N+1-Problem der ReflectionMethod-Aufrufe). Drei Shortcode-Klassen, je eine pro Endpunkt. Template-/Logik-Trennung: Templates bekommen vorbereitete Arrays, keine DB-Zugriffe im Template. Autorisierung zentral über ACF-Feld `webinar` (`field_692a7cabb8041`). Inline-Editor ersetzt Modal. Mobile (<768 px) klappt Tabellen zu `.dgptm-card`-Karten.

**Tech Stack:** WordPress 5.8+, PHP 7.4+, ACF (Advanced Custom Fields), Vanilla JS (jQuery vorhanden), CSS Custom Properties (Dashboard-Tokens). Kein Chart.js, kein Build-Schritt. Kein Test-Framework — Verifikation erfolgt manuell via WordPress-Admin/Browser gemäß CLAUDE.md des Projekts.

**Spec:** `docs/superpowers/specs/2026-04-20-vimeo-webinar-manager-dashboard-design.md`

**Hinweis zur Testmethodik:** Da das Projekt kein Test-Framework hat (siehe `DGPTMSuite/CLAUDE.md`), ersetzen wir den klassischen TDD-Loop durch: **Akzeptanzkriterium → Implementation → manueller Smoke-Test im Browser/Admin → Commit**. Jeder Task mit User-sichtbarer Änderung enthält einen expliziten manuellen Testschritt mit konkreten Klickpfaden.

---

## Phase 0 — Aufräumen

### Task 0.1: Altkopie `modules/content/vimeo-webinare/` löschen

**Files:**
- Delete: `modules/content/vimeo-webinare/` (gesamter Ordner)

- [ ] **Step 1: Prüfen, ob das Modul irgendwo als Abhängigkeit referenziert wird**

Run:
```bash
grep -rn "content/vimeo-webinare" --include="*.php" --include="*.json" modules/ core/ admin/ dgptm-master.php 2>/dev/null
```

Expected: Keine Treffer (content/vimeo-webinare ist eine isolierte Altkopie).

- [ ] **Step 2: Verzeichnis entfernen**

Run:
```bash
git rm -rf "modules/content/vimeo-webinare/"
```

- [ ] **Step 3: Commit**

```bash
git commit -m "$(cat <<'EOF'
chore(vimeo-webinare): Altkopie content/vimeo-webinare entfernt

Nur modules/media/vimeo-webinare/ bleibt kanonisch (v1.3.1 → v2.0.0).

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 0.2: Altdokumentation im Modul eindampfen

**Files:**
- Delete: `modules/media/vimeo-webinare/BATCH-IMPORT-ANLEITUNG.md`
- Delete: `modules/media/vimeo-webinare/CHANGELOG-1.3.0.md`
- Delete: `modules/media/vimeo-webinare/DEBUG-COMPLETION.md`
- Delete: `modules/media/vimeo-webinare/DEBUGGING.md`
- Delete: `modules/media/vimeo-webinare/INSTALLATION.md`
- Delete: `modules/media/vimeo-webinare/QUICK-REFERENCE.md`
- Delete: `modules/media/vimeo-webinare/QUICKSTART-V1.1.md`
- Delete: `modules/media/vimeo-webinare/STRUCTURE.md`
- Delete: `modules/media/vimeo-webinare/TESTING-GUIDE.md`
- Delete: `modules/media/vimeo-webinare/UPDATE-V1.1.md`
- Delete: `modules/media/vimeo-webinare/UPDATE-V1.2.md`
- Delete: `modules/media/vimeo-webinare/VERSION-1.2.4-SUMMARY.md`
- Modify: `modules/media/vimeo-webinare/README.md`
- Create: `modules/media/vimeo-webinare/CHANGELOG.md` (neu, falls nicht schon vorhanden — prüfen)

- [ ] **Step 1: Alte Doku-Dateien löschen**

Run:
```bash
cd "modules/media/vimeo-webinare"
git rm BATCH-IMPORT-ANLEITUNG.md CHANGELOG-1.3.0.md DEBUG-COMPLETION.md DEBUGGING.md INSTALLATION.md QUICK-REFERENCE.md QUICKSTART-V1.1.md STRUCTURE.md TESTING-GUIDE.md UPDATE-V1.1.md UPDATE-V1.2.md VERSION-1.2.4-SUMMARY.md
cd -
```

- [ ] **Step 2: README.md durch kompakte Version ersetzen**

Ersetze kompletten Inhalt von `modules/media/vimeo-webinare/README.md`:

```markdown
# DGPTM Vimeo-Webinare

WordPress-Modul der DGPTM Plugin Suite. Stellt Vimeo-Videos als
fortbildungsfähige Webinare bereit.

## Shortcodes

| Shortcode | Zweck | Ort |
|---|---|---|
| `[vimeo_webinar_liste]` | Öffentlicher Katalog für Mitglieder | Frontend-Seite (z. B. `/webinare/`) |
| `[vimeo_webinar_manager]` | Anlegen / Bearbeiten / Löschen von Webinaren | Tab im Mitglieder-Dashboard |
| `[vimeo_webinar_statistiken]` | Kennzahlen und Performance-Tabelle | Tab im Mitglieder-Dashboard |
| `[vimeo_webinar id="..."]` | Einzelner Player (wird auch per `/wissen/webinar/{id}` gerendert) | intern |

## Berechtigung

Der Manager- und Statistiken-Tab prüft das ACF-Feld `webinar`
(`field_692a7cabb8041`, Gruppe „Berechtigungen") am User. Nur User mit
`webinar = true` sehen den Dashboard-Tab und dürfen die schreibenden AJAX-Calls
ausführen.

## Abhängigkeiten

- Advanced Custom Fields (Pro)
- FPDF (für PDF-Zertifikate, vendored unter `fpdf/`)

## Struktur

- `includes/` — Klassen (Repository, Shortcodes, Vimeo-API)
- `templates/` — Präsentations-Templates (reine HTML-Ausgabe)
- `assets/css/` — Dashboard-konforme Stylesheets
- `assets/js/` — Frontend-Skripte (kein Build)

Siehe `CHANGELOG.md` für Versionshistorie.
```

- [ ] **Step 3: CHANGELOG.md prüfen / anlegen**

Prüfen, ob `CHANGELOG.md` bereits existiert (sowohl `CHANGELOG.md` als auch `CHANGELOG-1.3.0.md` hatten wir gesehen). Wenn nur noch `CHANGELOG.md` da ist: erhalten und unten Abschnitt hinzufügen. Wenn keiner: neu anlegen.

Lies aktuellen Stand:
```bash
test -f "modules/media/vimeo-webinare/CHANGELOG.md" && head -30 "modules/media/vimeo-webinare/CHANGELOG.md"
```

Wenn existiert, oberhalb des ersten `##` einfügen:

```markdown
## [2.0.0] — 2026-04-20

### Changed
- Shortcode-Struktur aufgeteilt: `[vimeo_webinar_liste]` (Frontend),
  `[vimeo_webinar_manager]` (Admin), `[vimeo_webinar_statistiken]` (neu).
- Manager und Statistiken sind jetzt als Tabs im Mitglieder-Dashboard vorgesehen.
- UI komplett an Dashboard-Design angeglichen (Tokens `--dd-*`, Dashicons statt
  Emojis, `.dgptm-card`-Komponenten).
- Inline-Editor ersetzt Modal-Dialog im Manager.
- Mobile-Layout (<768 px): Tabellen klappen zu Karten.
- N+1-Problem der Statistik-Abfrage behoben (Repository + `get_stats_batch`).

### Added
- ACF-basierte Autorisierung via Feld `webinar`.
- `dgptm-badge--muted`, `dgptm-btn--ghost`, `dgptm-toast`, `dgptm-progress`
  als neue Dashboard-kompatible Komponenten.

### Removed
- Stats-Tab aus `[vimeo_webinar_manager]` (jetzt eigener Shortcode).
- Modal-Dialoge aus dem Manager.
- `ReflectionMethod`-Zugriffe aus Templates.
```

Wenn keine CHANGELOG.md existiert, lege sie mit obigem Block plus Header an:

```markdown
# Changelog

Alle wesentlichen Änderungen werden hier festgehalten.

## [2.0.0] — 2026-04-20
...
```

- [ ] **Step 4: Manueller Check**

Run:
```bash
ls "modules/media/vimeo-webinare/"
```

Expected: `README.md`, `CHANGELOG.md`, `module.json`, `dgptm-vimeo-webinare.php`, `assets/`, `includes/`, `templates/`. **Keine** der gelöschten `.md`-Dateien mehr.

- [ ] **Step 5: Commit**

```bash
git add -A "modules/media/vimeo-webinare/"
git commit -m "$(cat <<'EOF'
chore(vimeo-webinare): Altdoku eingedampft, README/CHANGELOG konsolidiert

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Phase 1 — Infrastruktur

### Task 1.1: Repository-Klasse `class-webinar-repository.php` anlegen

**Files:**
- Create: `modules/media/vimeo-webinare/includes/class-webinar-repository.php`

- [ ] **Step 1: Neue Klasse anlegen**

Schreibe `modules/media/vimeo-webinare/includes/class-webinar-repository.php`:

```php
<?php
/**
 * Repository für Webinar-Daten.
 *
 * Einziger Ort, der ACF-Felder liest/schreibt und Stats aggregiert.
 * Löst das N+1-Problem der bisherigen ReflectionMethod-Aufrufe.
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('DGPTM_VW_Webinar_Repository')) {

    class DGPTM_VW_Webinar_Repository {

        private static $instance = null;

        public static function get_instance() {
            if (null === self::$instance) self::$instance = new self();
            return self::$instance;
        }

        private function __construct() {}

        /**
         * Alle publizierten Webinare mit ACF-Feldern.
         *
         * @return array[] Liste mit keys: id, title, description, vimeo_id,
         *                                  ebcp_points, completion_percentage, vnr
         */
        public function get_all(): array {
            $posts = get_posts([
                'post_type'      => 'vimeo_webinar',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'orderby'        => 'title',
                'order'          => 'ASC',
            ]);

            $out = [];
            foreach ($posts as $p) {
                $out[] = $this->map_post($p);
            }
            return $out;
        }

        /**
         * Einzelnes Webinar laden.
         */
        public function get(int $id): ?array {
            $p = get_post($id);
            if (!$p || $p->post_type !== 'vimeo_webinar') return null;
            return $this->map_post($p);
        }

        private function map_post(\WP_Post $p): array {
            return [
                'id'                     => $p->ID,
                'title'                  => $p->post_title,
                'description'            => $p->post_content,
                'vimeo_id'               => (string) get_field('vimeo_id', $p->ID),
                'ebcp_points'            => (float) (get_field('ebcp_points', $p->ID) ?: 1),
                'completion_percentage'  => (int) (get_field('completion_percentage', $p->ID) ?: 90),
                'vnr'                    => (string) get_field('vnr', $p->ID),
            ];
        }

        /**
         * Batch-Stats für viele Webinare mit EINER User-Meta-Abfrage.
         *
         * @param int[] $ids Webinar-IDs
         * @return array{int, array{completed:int, in_progress:int, total_views:int}}
         */
        public function get_stats_batch(array $ids): array {
            global $wpdb;

            $result = [];
            foreach ($ids as $id) {
                $result[(int) $id] = ['completed' => 0, 'in_progress' => 0, 'total_views' => 0];
            }
            if (empty($ids)) return $result;

            // Abschlüsse (_vw_completed_{id} = 1)
            $completed_placeholders = [];
            foreach ($ids as $id) {
                $completed_placeholders[] = '_vw_completed_' . intval($id);
            }
            $in_clause = "'" . implode("','", array_map('esc_sql', $completed_placeholders)) . "'";

            $completed_rows = $wpdb->get_results(
                "SELECT meta_key, COUNT(*) AS cnt FROM {$wpdb->usermeta}
                 WHERE meta_key IN ($in_clause) AND meta_value = '1'
                 GROUP BY meta_key",
                ARRAY_A
            );
            foreach ($completed_rows as $row) {
                $id = (int) str_replace('_vw_completed_', '', $row['meta_key']);
                if (isset($result[$id])) $result[$id]['completed'] = (int) $row['cnt'];
            }

            // Fortschritt > 0 und nicht abgeschlossen = In Bearbeitung
            $progress_placeholders = [];
            foreach ($ids as $id) {
                $progress_placeholders[] = '_vw_progress_' . intval($id);
            }
            $in_clause_p = "'" . implode("','", array_map('esc_sql', $progress_placeholders)) . "'";

            $progress_rows = $wpdb->get_results(
                "SELECT meta_key, COUNT(*) AS cnt FROM {$wpdb->usermeta}
                 WHERE meta_key IN ($in_clause_p) AND CAST(meta_value AS DECIMAL(5,2)) > 0
                 GROUP BY meta_key",
                ARRAY_A
            );
            foreach ($progress_rows as $row) {
                $id = (int) str_replace('_vw_progress_', '', $row['meta_key']);
                if (isset($result[$id])) {
                    $views = (int) $row['cnt'];
                    $completed = $result[$id]['completed'];
                    $result[$id]['total_views'] = $views;
                    $result[$id]['in_progress'] = max(0, $views - $completed);
                }
            }

            return $result;
        }

        /**
         * Aggregat: gewichtete Durchschnitts-Abschlussrate über alle Webinare.
         */
        public function get_average_completion_rate(): float {
            $ids = wp_list_pluck($this->get_all(), 'id');
            $stats = $this->get_stats_batch($ids);

            $total_completed = 0;
            $total_views = 0;
            foreach ($stats as $s) {
                $total_completed += $s['completed'];
                $total_views += $s['total_views'];
            }
            if ($total_views === 0) return 0.0;
            return round($total_completed / $total_views * 100, 1);
        }

        /**
         * Anlegen oder aktualisieren.
         *
         * @param array $data  Erwartet: post_id (0=create), title, description, vimeo_id,
         *                     completion_percentage, points, vnr
         * @return int|WP_Error Post-ID oder Fehler
         */
        public function save(array $data) {
            $post_id = intval($data['post_id'] ?? 0);
            $title = sanitize_text_field($data['title'] ?? '');
            $description = wp_kses_post($data['description'] ?? '');
            $vimeo_id = sanitize_text_field($data['vimeo_id'] ?? '');
            $completion = max(1, min(100, intval($data['completion_percentage'] ?? 90)));
            $points = floatval($data['points'] ?? 1);
            $vnr = sanitize_text_field($data['vnr'] ?? '');

            if (empty($title)) return new WP_Error('empty_title', 'Titel fehlt');
            if (empty($vimeo_id)) return new WP_Error('empty_vimeo_id', 'Vimeo-ID fehlt');

            $postarr = [
                'post_type'    => 'vimeo_webinar',
                'post_status'  => 'publish',
                'post_title'   => $title,
                'post_content' => $description,
            ];
            if ($post_id > 0) {
                $postarr['ID'] = $post_id;
                $result = wp_update_post($postarr, true);
            } else {
                $result = wp_insert_post($postarr, true);
            }
            if (is_wp_error($result)) return $result;

            update_field('vimeo_id', $vimeo_id, $result);
            update_field('completion_percentage', $completion, $result);
            update_field('ebcp_points', $points, $result);
            update_field('vnr', $vnr, $result);

            return (int) $result;
        }

        /**
         * Trash (reversibel). Kein Force-Delete.
         */
        public function trash(int $id): bool {
            return (bool) wp_trash_post($id);
        }
    }
}
```

- [ ] **Step 2: Syntax-Check**

Run (falls PHP vorhanden):
```bash
/d/php/php -l "modules/media/vimeo-webinare/includes/class-webinar-repository.php"
```

Expected: `No syntax errors detected`

Alternativ VS Code / IDE PHP-Linter.

- [ ] **Step 3: Commit**

```bash
git add "modules/media/vimeo-webinare/includes/class-webinar-repository.php"
git commit -m "$(cat <<'EOF'
feat(vimeo-webinare): Repository-Klasse für Daten- und Stats-Zugriffe

Löst das N+1-Problem der bisherigen ReflectionMethod-Aufrufe durch
get_stats_batch(). Kapselt Write-Operationen (save, trash) mit
sauberer Input-Sanitization.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 1.2: Autorisierungs-Helper und Permission-Konstante

**Files:**
- Modify: `modules/media/vimeo-webinare/dgptm-vimeo-webinare.php` (Header-Bereich + neue Methode)

- [ ] **Step 1: Permission-Konstante ergänzen**

Öffne `modules/media/vimeo-webinare/dgptm-vimeo-webinare.php`. Nach dem `* Version: 1.3.1`-Header und den bestehenden Konstanten, vor der Klasse-Definition, füge ein:

```php
if (!defined('DGPTM_VW_PERMISSION_FIELD')) {
    define('DGPTM_VW_PERMISSION_FIELD', 'webinar');
}
```

Konkret: Suche in der Datei nach `class DGPTM_Vimeo_Webinare` (oder der Hauptklasse). Direkt davor die Konstante einfügen. Falls andere `define()`-Konstanten oben stehen, dort einreihen.

- [ ] **Step 2: Autorisierungs-Helper-Methode hinzufügen**

In der Hauptklasse `DGPTM_Vimeo_Webinare` (wurde bei Grep als Klasse identifiziert), als neue `public` Methode. Plaziere sie direkt nach dem `__construct()` (Zeile ~91 laut Grep):

```php
    /**
     * Autorisierungs-Prüfung für schreibende Manager-Operationen.
     *
     * Prüft eingeloggt + ACF-Feld DGPTM_VW_PERMISSION_FIELD am User.
     * Bewusst kein current_user_can() / Rollencheck — Berechtigung
     * ist Sache der ACF-Konfiguration im User-Profil.
     */
    public function user_can_manage_webinars(): bool {
        if (!is_user_logged_in()) return false;
        if (!function_exists('get_field')) return false;
        return (bool) get_field(
            DGPTM_VW_PERMISSION_FIELD,
            'user_' . get_current_user_id()
        );
    }
```

- [ ] **Step 3: Include der Repository-Klasse im Konstruktor**

Im `__construct()` der Hauptklasse, **ganz oben** (vor den `add_action`/`add_shortcode`-Calls), Require einfügen:

```php
        require_once plugin_dir_path(__FILE__) . 'includes/class-webinar-repository.php';
```

Prüfe, ob `include/class-vimeo-api.php` schon geladen wird — falls ja: ähnliches Muster. Wenn `require_once` an anderer Stelle aufgerufen wird, füge dort den Repository-Require in der gleichen Nachbarschaft ein.

- [ ] **Step 4: Syntax-Check**

Run:
```bash
/d/php/php -l "modules/media/vimeo-webinare/dgptm-vimeo-webinare.php"
```

Expected: `No syntax errors detected`

- [ ] **Step 5: Commit**

```bash
git add "modules/media/vimeo-webinare/dgptm-vimeo-webinare.php"
git commit -m "$(cat <<'EOF'
feat(vimeo-webinare): ACF-basierte Autorisierung + Repository-Include

Konstante DGPTM_VW_PERMISSION_FIELD = 'webinar' und Helper
user_can_manage_webinars() zur zentralen Prüfung gegen das ACF-Feld.
Repository-Klasse wird im Bootstrap required.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Phase 2 — Design-Integration CSS

### Task 2.1: `dashboard-integration.css` mit Tokens und neuen Komponenten

**Files:**
- Create: `modules/media/vimeo-webinare/assets/css/dashboard-integration.css`

- [ ] **Step 1: Datei anlegen**

Inhalt von `modules/media/vimeo-webinare/assets/css/dashboard-integration.css`:

```css
/*
 * Dashboard-Design-Tokens für vimeo-webinare.
 * Spiegelt die Tokens aus modules/business/mitglieder-dashboard/assets/css/dashboard.css,
 * damit der Frontend-Katalog auch auf Seiten ohne aktives Dashboard-Modul
 * konsistent aussieht.
 */
.dgptm-vw {
    --dd-primary: #005792;
    --dd-primary-dark: #004577;
    --dd-primary-light: #e8f0f8;
    --dd-accent: #bd1622;
    --dd-accent-light: #fce8ea;
    --dd-border: #e2e6ea;
    --dd-text: #1d2327;
    --dd-muted: #646970;
    --dd-bg: #f5f7fa;
    --dd-card: #fff;
    --dd-radius: 6px;
    --dd-font: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    font-family: var(--dd-font);
    color: var(--dd-text);
}

/* Karte */
.dgptm-vw .dgptm-card {
    background: var(--dd-card);
    border: 1px solid var(--dd-border);
    border-radius: var(--dd-radius);
    padding: 16px;
    margin-bottom: 16px;
}
.dgptm-vw .dgptm-card h3 { margin: 0 0 10px; font-size: 15px; }

/* Data-Liste (dt/dd-Grid) */
.dgptm-vw .dgptm-data-list {
    display: grid;
    grid-template-columns: 130px 1fr;
    gap: 4px 12px;
    font-size: 14px;
}
.dgptm-vw .dgptm-data-list dt { color: var(--dd-muted); }
.dgptm-vw .dgptm-data-list dd { margin: 0; }

/* Badges */
.dgptm-vw .dgptm-badge {
    display: inline-block;
    padding: 3px 9px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    line-height: 1.4;
}
.dgptm-vw .dgptm-badge--primary { background: var(--dd-primary-light); color: var(--dd-primary); }
.dgptm-vw .dgptm-badge--success { background: #ecfdf5; color: #059669; }
.dgptm-vw .dgptm-badge--accent  { background: var(--dd-accent-light); color: var(--dd-accent); }
.dgptm-vw .dgptm-badge--muted   { background: var(--dd-bg); color: var(--dd-muted); }

/* Buttons */
.dgptm-vw .dgptm-btn--primary,
.dgptm-vw a.dgptm-btn--primary,
.dgptm-vw button.dgptm-btn--primary {
    display: inline-block;
    padding: 8px 16px;
    background: var(--dd-primary);
    color: #fff !important;
    border: none;
    border-radius: var(--dd-radius);
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: background .15s;
}
.dgptm-vw .dgptm-btn--primary:hover { background: var(--dd-primary-dark); }

.dgptm-vw .dgptm-btn--ghost,
.dgptm-vw a.dgptm-btn--ghost,
.dgptm-vw button.dgptm-btn--ghost {
    display: inline-block;
    padding: 7px 15px;
    background: transparent;
    color: var(--dd-primary);
    border: 1px solid var(--dd-primary);
    border-radius: var(--dd-radius);
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: background .15s;
}
.dgptm-vw .dgptm-btn--ghost:hover { background: var(--dd-primary-light); }

/* Toast */
.dgptm-vw-toast-layer {
    position: fixed;
    top: 24px;
    right: 24px;
    z-index: 99999;
    display: flex;
    flex-direction: column;
    gap: 8px;
    pointer-events: none;
}
.dgptm-toast {
    background: var(--dd-card, #fff);
    border: 1px solid var(--dd-border, #e2e6ea);
    border-left-width: 3px;
    border-radius: 6px;
    padding: 10px 14px;
    font-size: 14px;
    box-shadow: 0 4px 12px rgba(0,0,0,.08);
    pointer-events: auto;
    opacity: 0;
    transform: translateY(-4px);
    transition: opacity .2s, transform .2s;
}
.dgptm-toast.is-visible { opacity: 1; transform: translateY(0); }
.dgptm-toast--success { border-left-color: #059669; }
.dgptm-toast--error   { border-left-color: var(--dd-accent, #bd1622); }

/* Progress-Bar */
.dgptm-progress {
    width: 100%;
    height: 6px;
    background: var(--dd-primary-light);
    border-radius: 999px;
    overflow: hidden;
}
.dgptm-progress-fill {
    height: 100%;
    background: var(--dd-primary);
    transition: width .3s;
}

/* Form-Controls (in Manager und Liste-Filter) */
.dgptm-vw input[type="text"],
.dgptm-vw input[type="number"],
.dgptm-vw textarea,
.dgptm-vw select {
    font-family: var(--dd-font);
    font-size: 14px;
    padding: 8px 10px;
    border: 1px solid var(--dd-border);
    border-radius: var(--dd-radius);
    background: var(--dd-card);
    color: var(--dd-text);
    width: 100%;
    box-sizing: border-box;
}
.dgptm-vw input[type="text"]:focus,
.dgptm-vw input[type="number"]:focus,
.dgptm-vw textarea:focus,
.dgptm-vw select:focus {
    outline: 2px solid var(--dd-primary);
    outline-offset: -1px;
    border-color: var(--dd-primary);
}

/* Dashicon-Helper */
.dgptm-vw .dashicons {
    vertical-align: middle;
    line-height: 1;
}
```

- [ ] **Step 2: Commit**

```bash
git add "modules/media/vimeo-webinare/assets/css/dashboard-integration.css"
git commit -m "$(cat <<'EOF'
feat(vimeo-webinare): Dashboard-Tokens + neue Komponenten (CSS)

Tokens dupliziert (bewusst), damit Frontend-Liste auf Seiten ohne
aktives Dashboard-Modul konsistent aussieht. Neue Komponenten:
dgptm-btn--ghost, dgptm-badge--muted, dgptm-toast, dgptm-progress.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Phase 3 — Manager-Shortcode

### Task 3.1: Shortcode-Manager-Klasse mit Rendering und AJAX

**Files:**
- Create: `modules/media/vimeo-webinare/includes/class-shortcode-manager.php`

- [ ] **Step 1: Klasse anlegen**

Inhalt von `modules/media/vimeo-webinare/includes/class-shortcode-manager.php`:

```php
<?php
/**
 * Shortcode [vimeo_webinar_manager] — Admin-Manager für Webinare.
 *
 * Rendert Liste + Inline-Editor. Alle schreibenden AJAX-Endpoints
 * prüfen Nonce + user_can_manage_webinars().
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('DGPTM_VW_Shortcode_Manager')) {

    class DGPTM_VW_Shortcode_Manager {

        const NONCE_ACTION = 'dgptm_vw_manager';

        private static $instance = null;

        public static function get_instance() {
            if (null === self::$instance) self::$instance = new self();
            return self::$instance;
        }

        private function __construct() {
            add_shortcode('vimeo_webinar_manager', [$this, 'render']);
            add_action('wp_ajax_dgptm_vw_save',    [$this, 'ajax_save']);
            add_action('wp_ajax_dgptm_vw_delete',  [$this, 'ajax_delete']);
            add_action('wp_ajax_dgptm_vw_get_row', [$this, 'ajax_get_row']);
        }

        public function render($atts): string {
            if (!is_user_logged_in()) {
                return '<p>Bitte melden Sie sich an.</p>';
            }
            $repo = DGPTM_VW_Webinar_Repository::get_instance();
            $webinars = $repo->get_all();
            $ids = array_map(function ($w) { return $w['id']; }, $webinars);
            $stats = $repo->get_stats_batch($ids);

            $nonce = wp_create_nonce(self::NONCE_ACTION);

            ob_start();
            include plugin_dir_path(DGPTM_SUITE_FILE) . 'modules/media/vimeo-webinare/templates/manager-liste.php';
            return ob_get_clean();
        }

        public function ajax_save() {
            $this->require_auth();

            $result = DGPTM_VW_Webinar_Repository::get_instance()->save($_POST);
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }
            $webinar = DGPTM_VW_Webinar_Repository::get_instance()->get($result);
            $stats   = DGPTM_VW_Webinar_Repository::get_instance()->get_stats_batch([$result]);

            wp_send_json_success([
                'id'   => $result,
                'row'  => $this->render_row($webinar, $stats[$result]),
            ]);
        }

        public function ajax_delete() {
            $this->require_auth();

            $id = intval($_POST['post_id'] ?? 0);
            if ($id <= 0) wp_send_json_error('Ungültige ID');

            if (!DGPTM_VW_Webinar_Repository::get_instance()->trash($id)) {
                wp_send_json_error('Löschen fehlgeschlagen');
            }
            wp_send_json_success(['id' => $id]);
        }

        public function ajax_get_row() {
            $this->require_auth();

            $id = intval($_POST['post_id'] ?? 0);
            $webinar = DGPTM_VW_Webinar_Repository::get_instance()->get($id);
            if (!$webinar) wp_send_json_error('Nicht gefunden');
            $stats = DGPTM_VW_Webinar_Repository::get_instance()->get_stats_batch([$id]);
            wp_send_json_success(['row' => $this->render_row($webinar, $stats[$id])]);
        }

        private function require_auth() {
            check_ajax_referer(self::NONCE_ACTION, 'nonce');
            $main = DGPTM_Vimeo_Webinare::get_instance();
            if (!$main->user_can_manage_webinars()) {
                wp_send_json_error('Keine Berechtigung');
            }
        }

        private function render_row(array $webinar, array $stats): string {
            ob_start();
            $w = $webinar;
            $s = $stats;
            include plugin_dir_path(DGPTM_SUITE_FILE) . 'modules/media/vimeo-webinare/templates/manager-row.php';
            return ob_get_clean();
        }
    }
}
```

- [ ] **Step 2: Klasse im Bootstrap requiren**

In `modules/media/vimeo-webinare/dgptm-vimeo-webinare.php`, im `__construct()` direkt nach dem Repository-Require:

```php
        require_once plugin_dir_path(__FILE__) . 'includes/class-shortcode-manager.php';
        DGPTM_VW_Shortcode_Manager::get_instance();
```

- [ ] **Step 3: Alte Shortcode-Registrierung des Managers entfernen**

In `dgptm-vimeo-webinare.php` Zeile 108 (laut Grep) die Zeile:
```php
        add_shortcode('vimeo_webinar_manager', [$this, 'webinar_manager_shortcode']);
```
→ **löschen**.

Die Methode `webinar_manager_shortcode` (ab Zeile 700) kann vorerst stehenbleiben (wird in Task 6.1 entfernt), der Shortcode wird jetzt von der neuen Klasse registriert.

- [ ] **Step 4: Alte Manager-AJAX-Handler deregistrieren**

In `dgptm-vimeo-webinare.php` die Zeilen 115–118 entfernen:
```php
        add_action('wp_ajax_vw_manager_create', [$this, 'ajax_manager_create']);
        add_action('wp_ajax_vw_manager_update', [$this, 'ajax_manager_update']);
        add_action('wp_ajax_vw_manager_delete', [$this, 'ajax_manager_delete']);
        add_action('wp_ajax_vw_manager_stats',  [$this, 'ajax_manager_stats']);
```

Die Methoden selbst bleiben noch stehen (Task 6.1 entfernt sie).

- [ ] **Step 5: Syntax-Check**

```bash
/d/php/php -l "modules/media/vimeo-webinare/dgptm-vimeo-webinare.php"
/d/php/php -l "modules/media/vimeo-webinare/includes/class-shortcode-manager.php"
```

- [ ] **Step 6: Commit**

```bash
git add modules/media/vimeo-webinare/
git commit -m "$(cat <<'EOF'
feat(vimeo-webinare): Shortcode-Manager als eigene Klasse

Registriert [vimeo_webinar_manager] + AJAX-Endpoints dgptm_vw_save,
dgptm_vw_delete, dgptm_vw_get_row. Permission-Check über
user_can_manage_webinars() (ACF-Feld 'webinar').

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 3.2: Template `manager-liste.php` (Desktop-Tabelle)

**Files:**
- Create: `modules/media/vimeo-webinare/templates/manager-liste.php`
- Create: `modules/media/vimeo-webinare/templates/manager-row.php`

- [ ] **Step 1: Row-Template anlegen** (wird für initiales Rendering UND AJAX-Re-Render verwendet)

Inhalt `modules/media/vimeo-webinare/templates/manager-row.php`:

```php
<?php
/**
 * Template: Eine Zeile in der Manager-Tabelle.
 * Variablen: $w (Webinar-Array), $s (Stats-Array)
 */
if (!defined('ABSPATH')) exit;

$player_url = home_url('/wissen/webinar/' . $w['id']);
?>
<tr class="dgptm-vw-mgr-row" data-id="<?php echo esc_attr($w['id']); ?>" data-title="<?php echo esc_attr(strtolower($w['title'])); ?>">
    <td class="dgptm-vw-cell-title"><strong><?php echo esc_html($w['title']); ?></strong></td>
    <td><?php echo esc_html($w['vimeo_id']); ?></td>
    <td><?php echo esc_html(number_format_i18n($w['ebcp_points'], 1)); ?></td>
    <td><?php echo esc_html($w['completion_percentage']); ?>%</td>
    <td><?php echo esc_html($s['completed']); ?></td>
    <td class="dgptm-vw-cell-actions">
        <button type="button" class="dgptm-vw-btn-icon dgptm-vw-edit" data-id="<?php echo esc_attr($w['id']); ?>" title="Bearbeiten">
            <span class="dashicons dashicons-edit"></span>
        </button>
        <a href="<?php echo esc_url($player_url); ?>" class="dgptm-vw-btn-icon" title="Ansehen" target="_blank" rel="noopener">
            <span class="dashicons dashicons-visibility"></span>
        </a>
        <button type="button" class="dgptm-vw-btn-icon dgptm-vw-delete" data-id="<?php echo esc_attr($w['id']); ?>" title="In Papierkorb">
            <span class="dashicons dashicons-trash"></span>
        </button>
    </td>
</tr>
```

- [ ] **Step 2: Liste-Template anlegen**

Inhalt `modules/media/vimeo-webinare/templates/manager-liste.php`:

```php
<?php
/**
 * Template: Manager-Liste + Inline-Editor-Slot
 * Variablen: $webinars (array), $stats (array), $nonce (string)
 */
if (!defined('ABSPATH')) exit;
?>
<div class="dgptm-vw dgptm-vw-mgr" data-nonce="<?php echo esc_attr($nonce); ?>">

    <div class="dgptm-vw-mgr-toolbar">
        <label class="dgptm-vw-search">
            <span class="dashicons dashicons-search" aria-hidden="true"></span>
            <input type="text" class="dgptm-vw-mgr-search-input" placeholder="Webinare durchsuchen..." />
        </label>
        <button type="button" class="dgptm-btn--primary dgptm-vw-create-new">
            <span class="dashicons dashicons-plus-alt" aria-hidden="true"></span>
            Neues Webinar
        </button>
    </div>

    <!-- Editor-Slot für Create (oberhalb der Tabelle) -->
    <div class="dgptm-vw-editor-slot dgptm-vw-editor-create" hidden></div>

    <table class="dgptm-vw-mgr-table">
        <thead>
            <tr>
                <th>Titel</th>
                <th>Vimeo-ID</th>
                <th>EBCP</th>
                <th>Erforderlich</th>
                <th>Abgeschlossen</th>
                <th class="dgptm-vw-col-actions">Aktionen</th>
            </tr>
        </thead>
        <tbody class="dgptm-vw-mgr-tbody">
            <?php foreach ($webinars as $w):
                $s = $stats[$w['id']] ?? ['completed' => 0, 'in_progress' => 0, 'total_views' => 0];
                include plugin_dir_path(__FILE__) . 'manager-row.php';
            endforeach; ?>
        </tbody>
    </table>

    <?php if (empty($webinars)): ?>
        <p class="dgptm-vw-empty">Keine Webinare vorhanden.</p>
    <?php endif; ?>

</div>

<div class="dgptm-vw-toast-layer" aria-live="polite"></div>
```

- [ ] **Step 3: Manueller Smoke-Test (nur Rendering, ohne JS)**

1. Modul aktivieren (falls nicht aktiv): WP-Admin → DGPTM Plugin Suite → `vimeo-webinare` aktivieren.
2. Eine neue Seite anlegen „Webinar-Verwaltung Test", Inhalt: `[vimeo_webinar_manager]`.
3. Als eingeloggter Admin aufrufen.
4. Erwartung: Tabelle rendert (ggf. unstyliert), Header korrekt, mindestens die bestehenden Webinare sichtbar. Keine PHP-Fehler im `debug.log`.
5. Ohne Login aufrufen: Erwartung: „Bitte melden Sie sich an."

- [ ] **Step 4: Commit**

```bash
git add modules/media/vimeo-webinare/templates/
git commit -m "$(cat <<'EOF'
feat(vimeo-webinare): Templates manager-liste + manager-row

Rein-präsentationelle Templates, bekommen Arrays per include.
Keine DB-Zugriffe, keine Reflection.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 3.3: Template `manager-form.php` (Inline-Formular)

**Files:**
- Create: `modules/media/vimeo-webinare/templates/manager-form.php`

- [ ] **Step 1: Formular-Template anlegen**

Inhalt `modules/media/vimeo-webinare/templates/manager-form.php`:

```php
<?php
/**
 * Template: Inline-Formular für Create/Edit eines Webinars.
 *
 * Wird auch client-seitig als Template-HTML in data-Attribut gepusht —
 * deswegen hier eine Rein-Server-Variante. Die JS-Variante in manager.js
 * muss identisch bleiben (Synchronisation manuell prüfen).
 *
 * Variablen: $w (optional: bestehendes Webinar für Edit, sonst leer)
 */
if (!defined('ABSPATH')) exit;

$w = $w ?? [
    'id' => 0, 'title' => '', 'description' => '', 'vimeo_id' => '',
    'completion_percentage' => 90, 'ebcp_points' => 1, 'vnr' => '',
];
$is_edit = $w['id'] > 0;
?>
<form class="dgptm-vw-form dgptm-card">
    <h3><?php echo $is_edit ? 'Webinar bearbeiten' : 'Neues Webinar'; ?></h3>
    <input type="hidden" name="post_id" value="<?php echo esc_attr($w['id']); ?>" />

    <div class="dgptm-vw-form-row">
        <label class="dgptm-vw-form-field dgptm-vw-form-field-full">
            <span>Titel <em>*</em></span>
            <input type="text" name="title" required value="<?php echo esc_attr($w['title']); ?>" />
        </label>
    </div>

    <div class="dgptm-vw-form-row">
        <label class="dgptm-vw-form-field dgptm-vw-form-field-full">
            <span>Beschreibung</span>
            <textarea name="description" rows="3"><?php echo esc_textarea($w['description']); ?></textarea>
        </label>
    </div>

    <div class="dgptm-vw-form-row dgptm-vw-form-row-split">
        <label class="dgptm-vw-form-field">
            <span>Vimeo-ID <em>*</em></span>
            <input type="text" name="vimeo_id" required value="<?php echo esc_attr($w['vimeo_id']); ?>" />
            <small>Nur die Zahlen-ID</small>
        </label>
        <label class="dgptm-vw-form-field">
            <span>Erforderlich % <em>*</em></span>
            <input type="number" name="completion_percentage" min="1" max="100" value="<?php echo esc_attr($w['completion_percentage']); ?>" required />
        </label>
    </div>

    <div class="dgptm-vw-form-row dgptm-vw-form-row-split">
        <label class="dgptm-vw-form-field">
            <span>EBCP-Punkte <em>*</em></span>
            <input type="number" name="points" step="0.5" min="0" value="<?php echo esc_attr($w['ebcp_points']); ?>" required />
        </label>
        <label class="dgptm-vw-form-field">
            <span>VNR</span>
            <input type="text" name="vnr" value="<?php echo esc_attr($w['vnr']); ?>" />
        </label>
    </div>

    <div class="dgptm-vw-form-actions">
        <button type="button" class="dgptm-btn--ghost dgptm-vw-form-cancel">Abbrechen</button>
        <button type="submit" class="dgptm-btn--primary dgptm-vw-form-save">Speichern</button>
    </div>
</form>
```

- [ ] **Step 2: Commit**

```bash
git add modules/media/vimeo-webinare/templates/manager-form.php
git commit -m "$(cat <<'EOF'
feat(vimeo-webinare): Inline-Formular für Create/Edit

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 3.4: `manager.css` — Desktop-Tabelle + Toolbar + Formular

**Files:**
- Create: `modules/media/vimeo-webinare/assets/css/manager.css`

- [ ] **Step 1: Stylesheet anlegen**

Inhalt `modules/media/vimeo-webinare/assets/css/manager.css`:

```css
/* Manager — Desktop-Tabelle + Toolbar + Inline-Formular */

.dgptm-vw-mgr { max-width: 100%; }

.dgptm-vw-mgr-toolbar {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}
.dgptm-vw-search {
    position: relative;
    flex: 1 1 280px;
    max-width: 420px;
}
.dgptm-vw-search .dashicons {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--dd-muted);
    pointer-events: none;
}
.dgptm-vw-search input {
    padding-left: 34px;
}

/* Tabelle */
.dgptm-vw-mgr-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    background: var(--dd-card);
    border: 1px solid var(--dd-border);
    border-radius: var(--dd-radius);
    overflow: hidden;
    font-size: 14px;
}
.dgptm-vw-mgr-table th,
.dgptm-vw-mgr-table td {
    padding: 10px 12px;
    text-align: left;
    border-bottom: 1px solid var(--dd-border);
}
.dgptm-vw-mgr-table thead th {
    background: var(--dd-bg);
    font-weight: 600;
    color: var(--dd-muted);
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: .03em;
}
.dgptm-vw-mgr-table tbody tr:hover { background: var(--dd-primary-light); }
.dgptm-vw-mgr-table tbody tr:last-child td { border-bottom: none; }
.dgptm-vw-col-actions { text-align: right; }

/* Action-Icons */
.dgptm-vw-btn-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border: none;
    background: transparent;
    color: var(--dd-muted);
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    transition: background .15s, color .15s;
}
.dgptm-vw-btn-icon:hover { background: var(--dd-primary-light); color: var(--dd-primary); }
.dgptm-vw-btn-icon.dgptm-vw-delete:hover { background: var(--dd-accent-light); color: var(--dd-accent); }

/* Empty-State */
.dgptm-vw-empty {
    text-align: center;
    color: var(--dd-muted);
    padding: 40px 0;
}

/* Inline-Editor-Slots */
.dgptm-vw-editor-slot { margin-bottom: 16px; }
.dgptm-vw-editor-row td { padding: 0 !important; background: var(--dd-bg); }
.dgptm-vw-editor-row .dgptm-vw-form { margin: 12px; }

/* Formular */
.dgptm-vw-form h3 { margin-top: 0; }
.dgptm-vw-form-row { margin-bottom: 12px; }
.dgptm-vw-form-row-split {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.dgptm-vw-form-field { display: block; }
.dgptm-vw-form-field > span {
    display: block;
    font-weight: 600;
    font-size: 13px;
    margin-bottom: 4px;
    color: var(--dd-text);
}
.dgptm-vw-form-field em { color: var(--dd-accent); font-style: normal; }
.dgptm-vw-form-field small { color: var(--dd-muted); font-size: 12px; }
.dgptm-vw-form-field-full { grid-column: 1 / -1; }

.dgptm-vw-form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 16px;
    padding-top: 12px;
    border-top: 1px solid var(--dd-border);
}

/* Loading-State am Save-Button */
.dgptm-vw-form.is-loading .dgptm-vw-form-save {
    opacity: .6;
    pointer-events: none;
}

/* --- Mobile (<768px): Tabelle zu Karten --- */
@media (max-width: 768px) {
    .dgptm-vw-mgr-table thead { display: none; }
    .dgptm-vw-mgr-table,
    .dgptm-vw-mgr-table tbody,
    .dgptm-vw-mgr-table tr,
    .dgptm-vw-mgr-table td {
        display: block;
        width: 100%;
        box-sizing: border-box;
    }
    .dgptm-vw-mgr-table {
        border: none;
        background: transparent;
    }
    .dgptm-vw-mgr-table tbody tr {
        background: var(--dd-card);
        border: 1px solid var(--dd-border);
        border-radius: var(--dd-radius);
        margin-bottom: 12px;
        padding: 12px;
    }
    .dgptm-vw-mgr-table tbody tr:hover { background: var(--dd-card); }
    .dgptm-vw-mgr-table td {
        padding: 4px 0;
        border: none;
        display: grid;
        grid-template-columns: 130px 1fr;
        gap: 8px;
        font-size: 14px;
    }
    .dgptm-vw-mgr-table td.dgptm-vw-cell-title {
        grid-template-columns: 1fr;
        font-size: 16px;
        margin-bottom: 8px;
    }
    .dgptm-vw-mgr-table td:not(.dgptm-vw-cell-title):not(.dgptm-vw-cell-actions)::before {
        content: attr(data-label);
        color: var(--dd-muted);
        font-size: 13px;
    }
    .dgptm-vw-mgr-table td.dgptm-vw-cell-actions {
        grid-template-columns: 1fr;
        justify-items: end;
        margin-top: 8px;
        padding-top: 8px;
        border-top: 1px solid var(--dd-border);
    }
    .dgptm-vw-form-row-split { grid-template-columns: 1fr; }
    .dgptm-vw-mgr-toolbar { flex-direction: column; align-items: stretch; }
    .dgptm-vw-mgr-toolbar .dgptm-btn--primary { width: 100%; text-align: center; }
    .dgptm-vw-search { max-width: none; }
}
```

- [ ] **Step 2: Data-Labels in `manager-row.php` ergänzen (für Mobile-Cards)**

Öffne `modules/media/vimeo-webinare/templates/manager-row.php`. Ergänze an den `<td>`-Tags der Nicht-Titel-Spalten das `data-label`-Attribut für die Mobile-Darstellung:

Finde:
```php
    <td><?php echo esc_html($w['vimeo_id']); ?></td>
    <td><?php echo esc_html(number_format_i18n($w['ebcp_points'], 1)); ?></td>
    <td><?php echo esc_html($w['completion_percentage']); ?>%</td>
    <td><?php echo esc_html($s['completed']); ?></td>
```

Ersetze durch:
```php
    <td data-label="Vimeo-ID"><?php echo esc_html($w['vimeo_id']); ?></td>
    <td data-label="EBCP-Punkte"><?php echo esc_html(number_format_i18n($w['ebcp_points'], 1)); ?></td>
    <td data-label="Erforderlich"><?php echo esc_html($w['completion_percentage']); ?>%</td>
    <td data-label="Abgeschlossen"><?php echo esc_html($s['completed']); ?></td>
```

- [ ] **Step 3: Commit**

```bash
git add modules/media/vimeo-webinare/assets/css/manager.css modules/media/vimeo-webinare/templates/manager-row.php
git commit -m "$(cat <<'EOF'
feat(vimeo-webinare): Manager-CSS Desktop + Mobile-Cards

Tabelle klappt unter 768px zu Card-Layout mit data-label-Dekoration.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 3.5: `manager.js` — CRUD, Inline-Editor, Toast, Suche

**Files:**
- Create: `modules/media/vimeo-webinare/assets/js/manager.js`

- [ ] **Step 1: Script anlegen**

Inhalt `modules/media/vimeo-webinare/assets/js/manager.js`:

```javascript
/* Manager-Frontend: CRUD, Inline-Editor, Toast, Suche.
   Nutzt jQuery (bereits im WP-Admin vorhanden). */
(function ($) {
    'use strict';

    var FORM_TEMPLATE = null; // wird aus DOM-Template gelesen

    function ajaxCall(action, data, $form) {
        var $root = $('.dgptm-vw-mgr');
        var nonce = $root.data('nonce');
        var ajaxUrl = (window.ajaxurl) ? window.ajaxurl : '/wp-admin/admin-ajax.php';

        if ($form) $form.addClass('is-loading');

        return $.post(ajaxUrl, Object.assign({
            action: action,
            nonce: nonce,
        }, data)).always(function () {
            if ($form) $form.removeClass('is-loading');
        });
    }

    function toast(message, type) {
        type = type || 'success';
        var $t = $('<div class="dgptm-toast dgptm-toast--' + type + '"></div>').text(message);
        $('.dgptm-vw-toast-layer').append($t);
        requestAnimationFrame(function () { $t.addClass('is-visible'); });
        setTimeout(function () {
            $t.removeClass('is-visible');
            setTimeout(function () { $t.remove(); }, 250);
        }, 3000);
    }

    function getFormTemplate() {
        // Formular-HTML wird aus einem versteckten Template-Tag gelesen,
        // das serverseitig eingebettet wird. Falls kein Template vorhanden:
        // minimaler Fallback.
        if (FORM_TEMPLATE) return FORM_TEMPLATE;
        var $tpl = $('#dgptm-vw-form-template');
        FORM_TEMPLATE = $tpl.length ? $tpl.html() : '';
        return FORM_TEMPLATE;
    }

    function closeAllEditors() {
        $('.dgptm-vw-editor-slot').empty().attr('hidden', true);
        $('.dgptm-vw-editor-row').remove();
    }

    function confirmDiscardIfDirty() {
        var $open = $('.dgptm-vw-form');
        if ($open.length && $open.data('dirty')) {
            return window.confirm('Ungespeicherte Änderungen verwerfen?');
        }
        return true;
    }

    function populateForm($form, webinar) {
        $form.find('[name="post_id"]').val(webinar.id || 0);
        $form.find('[name="title"]').val(webinar.title || '');
        $form.find('[name="description"]').val(webinar.description || '');
        $form.find('[name="vimeo_id"]').val(webinar.vimeo_id || '');
        $form.find('[name="completion_percentage"]').val(webinar.completion_percentage || 90);
        $form.find('[name="points"]').val(webinar.ebcp_points || 1);
        $form.find('[name="vnr"]').val(webinar.vnr || '');
        $form.find('h3').text(webinar.id ? 'Webinar bearbeiten' : 'Neues Webinar');
        $form.data('dirty', false);
    }

    function markDirty() {
        $(this).closest('.dgptm-vw-form').data('dirty', true);
    }

    function openCreate() {
        if (!confirmDiscardIfDirty()) return;
        closeAllEditors();
        var html = getFormTemplate();
        var $slot = $('.dgptm-vw-editor-create');
        $slot.html(html).removeAttr('hidden');
        var $form = $slot.find('.dgptm-vw-form');
        populateForm($form, {});
        $form.find('[name="title"]').focus();
    }

    function openEdit(id) {
        if (!confirmDiscardIfDirty()) return;
        closeAllEditors();
        var $row = $('.dgptm-vw-mgr-row[data-id="' + id + '"]');
        var cols = $row.find('td').length || 6;
        var $editorRow = $('<tr class="dgptm-vw-editor-row"><td colspan="' + cols + '"></td></tr>');
        $editorRow.find('td').html(getFormTemplate());
        $row.after($editorRow);
        var $form = $editorRow.find('.dgptm-vw-form');

        // Daten aus DOM + AJAX-Nachladen für Beschreibung/VNR
        // Für hohe Korrektheit: via dgptm_vw_get_row kommen wir nur an das Row-HTML,
        // nicht an die Rohdaten. Daher zusätzliche AJAX-Action nicht nötig — wir
        // lesen sichtbare Felder aus der Zeile + lazy-load Rest via data-Attribut:
        var seed = {
            id: id,
            title: $row.find('.dgptm-vw-cell-title').text().trim(),
            vimeo_id: $row.find('td').eq(1).text().trim(),
            ebcp_points: parseFloat($row.find('td').eq(2).text().replace(',', '.')) || 1,
            completion_percentage: parseInt($row.find('td').eq(3).text(), 10) || 90,
            description: $row.data('description') || '',
            vnr: $row.data('vnr') || '',
        };
        populateForm($form, seed);
        $form.find('[name="title"]').focus();
    }

    function onSave(e) {
        e.preventDefault();
        var $form = $(this);
        var payload = {};
        $form.serializeArray().forEach(function (f) { payload[f.name] = f.value; });

        ajaxCall('dgptm_vw_save', payload, $form).then(function (resp) {
            if (!resp || !resp.success) {
                toast((resp && resp.data) || 'Speichern fehlgeschlagen', 'error');
                return;
            }
            var id = resp.data.id;
            var rowHtml = resp.data.row;
            var $existing = $('.dgptm-vw-mgr-row[data-id="' + id + '"]');
            if ($existing.length) {
                $existing.replaceWith(rowHtml);
            } else {
                $('.dgptm-vw-mgr-tbody').prepend(rowHtml);
            }
            closeAllEditors();
            toast('Gespeichert');
        }).fail(function () {
            toast('Serverfehler beim Speichern', 'error');
        });
    }

    function onDelete() {
        var id = $(this).data('id');
        if (!window.confirm('Webinar wirklich in den Papierkorb verschieben?')) return;
        ajaxCall('dgptm_vw_delete', { post_id: id }).then(function (resp) {
            if (!resp || !resp.success) {
                toast((resp && resp.data) || 'Löschen fehlgeschlagen', 'error');
                return;
            }
            $('.dgptm-vw-mgr-row[data-id="' + id + '"]').fadeOut(200, function () { $(this).remove(); });
            toast('In Papierkorb verschoben');
        }).fail(function () { toast('Serverfehler', 'error'); });
    }

    function onSearch() {
        var q = $(this).val().trim().toLowerCase();
        $('.dgptm-vw-mgr-row').each(function () {
            var title = $(this).data('title') || '';
            $(this).toggle(title.indexOf(q) !== -1);
        });
    }

    $(function () {
        // Event-Delegation auf Root-Container
        $(document).on('click', '.dgptm-vw-create-new', openCreate);
        $(document).on('click', '.dgptm-vw-edit', function () { openEdit($(this).data('id')); });
        $(document).on('click', '.dgptm-vw-delete', onDelete);
        $(document).on('click', '.dgptm-vw-form-cancel', function () {
            if (confirmDiscardIfDirty()) closeAllEditors();
        });
        $(document).on('submit', '.dgptm-vw-form', onSave);
        $(document).on('input change', '.dgptm-vw-form input, .dgptm-vw-form textarea', markDirty);
        $(document).on('input', '.dgptm-vw-mgr-search-input', onSearch);
    });
})(jQuery);
```

- [ ] **Step 2: Formular-Template in `manager-liste.php` einbetten**

In `modules/media/vimeo-webinare/templates/manager-liste.php` am Ende der `<div class="dgptm-vw dgptm-vw-mgr">`, **vor** dem schließenden `</div>`, einfügen:

```php
    <script type="text/template" id="dgptm-vw-form-template">
        <?php include plugin_dir_path(__FILE__) . 'manager-form.php'; ?>
    </script>
```

So wird das Formular-HTML einmalig im DOM verfügbar, aber nicht gerendert; `manager.js` liest es per `$('#dgptm-vw-form-template').html()`.

- [ ] **Step 3: Description und VNR als Data-Attribute in `manager-row.php` mitführen**

In `modules/media/vimeo-webinare/templates/manager-row.php`, am `<tr>`-Tag die Rohdaten für den Inline-Editor hinterlegen:

Finde:
```php
<tr class="dgptm-vw-mgr-row" data-id="<?php echo esc_attr($w['id']); ?>" data-title="<?php echo esc_attr(strtolower($w['title'])); ?>">
```

Ersetze durch:
```php
<tr class="dgptm-vw-mgr-row"
    data-id="<?php echo esc_attr($w['id']); ?>"
    data-title="<?php echo esc_attr(strtolower($w['title'])); ?>"
    data-description="<?php echo esc_attr($w['description']); ?>"
    data-vnr="<?php echo esc_attr($w['vnr']); ?>">
```

- [ ] **Step 4: Commit**

```bash
git add modules/media/vimeo-webinare/assets/js/manager.js modules/media/vimeo-webinare/templates/
git commit -m "$(cat <<'EOF'
feat(vimeo-webinare): Manager-JS (CRUD, Inline-Editor, Toast, Suche)

Inline-Editor mit dirty-Tracking und Bestätigungsdialog. Row-Daten für
Edit-Seeding im data-*-Attribut auf <tr>. Formular-Template als
script[type=text/template] im DOM.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 3.6: Asset-Enqueue für Manager

**Files:**
- Modify: `modules/media/vimeo-webinare/dgptm-vimeo-webinare.php` (Methode `enqueue_assets`, Zeile 533)

- [ ] **Step 1: Enqueue anpassen**

Öffne `dgptm-vimeo-webinare.php`, finde die Methode `public function enqueue_assets()` ab Zeile 533. Lies den bestehenden Block zunächst:

```bash
sed -n '533,580p' "modules/media/vimeo-webinare/dgptm-vimeo-webinare.php"
```

Identifiziere die Stellen, an denen `has_shortcode('vimeo_webinar_manager')` bzw. `'vimeo_webinar_liste'` geprüft werden. Ergänze/ersetze so, dass die neuen Assets geladen werden:

Innerhalb der `enqueue_assets()`-Methode: nach der bestehenden `has_shortcode`-Abfrage-Kette einfügen:

```php
        global $post;
        $content = isset($post->post_content) ? $post->post_content : '';

        // Gemeinsame Dashboard-Tokens — auf allen drei Shortcode-Seiten
        $needs_dashboard_css =
            has_shortcode($content, 'vimeo_webinar_manager') ||
            has_shortcode($content, 'vimeo_webinar_liste') ||
            has_shortcode($content, 'vimeo_webinar_statistiken');

        if ($needs_dashboard_css) {
            wp_enqueue_style(
                'dgptm-vw-dashboard-integration',
                plugin_dir_url(__FILE__) . 'assets/css/dashboard-integration.css',
                [],
                '2.0.0'
            );
            wp_enqueue_style('dashicons');
        }

        if (has_shortcode($content, 'vimeo_webinar_manager')) {
            wp_enqueue_style(
                'dgptm-vw-manager',
                plugin_dir_url(__FILE__) . 'assets/css/manager.css',
                ['dgptm-vw-dashboard-integration'],
                '2.0.0'
            );
            wp_enqueue_script(
                'dgptm-vw-manager',
                plugin_dir_url(__FILE__) . 'assets/js/manager.js',
                ['jquery'],
                '2.0.0',
                true
            );
        }
```

**Wichtig:** Die bestehenden `wp_enqueue_style`/`_script`-Aufrufe für die Alt-CSS (`style.css`, `admin-style.css`, `admin-import.css`) und Alt-JS (`script.js`, `admin-script.js`, `admin-import.js`) bleiben vorerst stehen, solange Seiten wie der Player oder die Admin-Subpages (Import/Einstellungen) sie noch brauchen. Entfernt werden sie erst wenn gesichert ist, dass sie ungenutzt sind — das prüft Task 6.1.

- [ ] **Step 2: Manueller Test**

1. Cache leeren (WP-Admin → WP Rocket → Cache leeren, oder Browser-Hard-Reload).
2. Seite mit `[vimeo_webinar_manager]` aufrufen (eingeloggt als Admin mit `webinar=true`).
3. DevTools → Network: `dashboard-integration.css`, `manager.css` und `manager.js` müssen geladen werden.
4. Visual-Check: Tabelle hat Dashboard-Optik (grau-blauer Header, Hover-Effekt primary-light).
5. „Neues Webinar"-Button: Klick öffnet Formular oberhalb der Tabelle.
6. Formular ausfüllen, Speichern: Toast erscheint oben rechts, neue Zeile in Tabelle.
7. Bearbeiten-Icon in bestehender Zeile: Formular klappt unter der Zeile auf. Speichern aktualisiert die Zeile live.
8. Löschen-Icon: Bestätigungs-Dialog, dann Zeile verschwindet, Toast „In Papierkorb verschoben". Im WP-Admin → Webinare → Papierkorb liegt der Post.
9. DevTools → Mobile-Ansicht (<768px): Tabelle wird zu Karten.
10. AJAX-Call ohne `webinar`-Flag: Im Browser-Profil das ACF-Feld auf `false` setzen, Seite neu laden, ein Klick auf „Speichern" → Toast „Keine Berechtigung".

- [ ] **Step 3: Commit**

```bash
git add modules/media/vimeo-webinare/dgptm-vimeo-webinare.php
git commit -m "$(cat <<'EOF'
feat(vimeo-webinare): Asset-Enqueue für Manager-Shortcode

Lädt dashboard-integration.css und manager.css/js nur wenn
Shortcode auf der Seite vorkommt.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Phase 4 — Statistiken-Shortcode

### Task 4.1: Shortcode-Statistiken-Klasse

**Files:**
- Create: `modules/media/vimeo-webinare/includes/class-shortcode-statistiken.php`

- [ ] **Step 1: Klasse anlegen**

Inhalt:

```php
<?php
/**
 * Shortcode [vimeo_webinar_statistiken] — Admin-Statistiken.
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('DGPTM_VW_Shortcode_Statistiken')) {

    class DGPTM_VW_Shortcode_Statistiken {

        private static $instance = null;

        public static function get_instance() {
            if (null === self::$instance) self::$instance = new self();
            return self::$instance;
        }

        private function __construct() {
            add_shortcode('vimeo_webinar_statistiken', [$this, 'render']);
        }

        public function render($atts): string {
            if (!is_user_logged_in()) {
                return '<p>Bitte melden Sie sich an.</p>';
            }
            $repo = DGPTM_VW_Webinar_Repository::get_instance();
            $webinars = $repo->get_all();
            $ids = array_map(function ($w) { return $w['id']; }, $webinars);
            $stats = $repo->get_stats_batch($ids);

            $total_webinars = count($webinars);
            $total_completed = 0;
            $total_in_progress = 0;
            $total_views = 0;
            foreach ($stats as $s) {
                $total_completed += $s['completed'];
                $total_in_progress += $s['in_progress'];
                $total_views += $s['total_views'];
            }
            $avg_rate = $repo->get_average_completion_rate();

            ob_start();
            include plugin_dir_path(DGPTM_SUITE_FILE) . 'modules/media/vimeo-webinare/templates/statistiken.php';
            return ob_get_clean();
        }
    }
}
```

- [ ] **Step 2: In Bootstrap requiren**

In `dgptm-vimeo-webinare.php` im `__construct()`, nach dem Shortcode-Manager-Require:

```php
        require_once plugin_dir_path(__FILE__) . 'includes/class-shortcode-statistiken.php';
        DGPTM_VW_Shortcode_Statistiken::get_instance();
```

- [ ] **Step 3: Commit**

```bash
git add modules/media/vimeo-webinare/includes/class-shortcode-statistiken.php modules/media/vimeo-webinare/dgptm-vimeo-webinare.php
git commit -m "$(cat <<'EOF'
feat(vimeo-webinare): Shortcode-Statistiken-Klasse

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 4.2: Template `statistiken.php`

**Files:**
- Create: `modules/media/vimeo-webinare/templates/statistiken.php`

- [ ] **Step 1: Template schreiben**

Inhalt `modules/media/vimeo-webinare/templates/statistiken.php`:

```php
<?php
/**
 * Template: Webinar-Statistiken.
 * Variablen: $webinars, $stats, $total_webinars, $total_completed,
 *            $total_in_progress, $total_views, $avg_rate
 */
if (!defined('ABSPATH')) exit;
?>
<div class="dgptm-vw dgptm-vw-stats">

    <div class="dgptm-vw-kpi-grid">
        <div class="dgptm-card dgptm-vw-kpi">
            <div class="dgptm-vw-kpi-number"><?php echo esc_html($total_webinars); ?></div>
            <div class="dgptm-vw-kpi-label">Gesamt Webinare</div>
        </div>
        <div class="dgptm-card dgptm-vw-kpi">
            <div class="dgptm-vw-kpi-number"><?php echo esc_html($total_completed); ?></div>
            <div class="dgptm-vw-kpi-label">Gesamt Abschlüsse</div>
        </div>
        <div class="dgptm-card dgptm-vw-kpi">
            <div class="dgptm-vw-kpi-number"><?php echo esc_html($total_in_progress); ?></div>
            <div class="dgptm-vw-kpi-label">In Bearbeitung</div>
        </div>
        <div class="dgptm-card dgptm-vw-kpi">
            <div class="dgptm-vw-kpi-number"><?php echo esc_html(number_format_i18n($avg_rate, 1)); ?>%</div>
            <div class="dgptm-vw-kpi-label">Abschlussrate Ø (gewichtet)</div>
        </div>
    </div>

    <?php if (!empty($webinars)): ?>
    <div class="dgptm-card dgptm-vw-performance">
        <h3>Performance</h3>
        <table class="dgptm-vw-stats-table">
            <thead>
                <tr>
                    <th data-sort="title">Webinar</th>
                    <th data-sort="completed">Abgeschlossen</th>
                    <th data-sort="in_progress">In Bearbeitung</th>
                    <th data-sort="views">Gesamt Ansichten</th>
                    <th data-sort="rate">Completion-Rate</th>
                    <th>Verlauf</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($webinars as $w):
                    $s = $stats[$w['id']] ?? ['completed' => 0, 'in_progress' => 0, 'total_views' => 0];
                    $rate = $s['total_views'] > 0
                        ? round($s['completed'] / $s['total_views'] * 100, 1)
                        : 0.0;
                ?>
                <tr data-rate="<?php echo esc_attr($rate); ?>"
                    data-completed="<?php echo esc_attr($s['completed']); ?>"
                    data-in_progress="<?php echo esc_attr($s['in_progress']); ?>"
                    data-views="<?php echo esc_attr($s['total_views']); ?>"
                    data-title="<?php echo esc_attr(strtolower($w['title'])); ?>">
                    <td data-label="Webinar"><?php echo esc_html($w['title']); ?></td>
                    <td data-label="Abgeschlossen"><?php echo esc_html($s['completed']); ?></td>
                    <td data-label="In Bearbeitung"><?php echo esc_html($s['in_progress']); ?></td>
                    <td data-label="Ansichten"><?php echo esc_html($s['total_views']); ?></td>
                    <td data-label="Rate"><?php echo esc_html(number_format_i18n($rate, 1)); ?>%</td>
                    <td data-label="Verlauf">
                        <div class="dgptm-vw-sparkline" style="--rate: <?php echo esc_attr($rate); ?>"></div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</div>
```

- [ ] **Step 2: Commit**

```bash
git add modules/media/vimeo-webinare/templates/statistiken.php
git commit -m "$(cat <<'EOF'
feat(vimeo-webinare): Template statistiken (KPI-Grid + Performance-Tabelle)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 4.3: `statistiken.css` und `statistiken.js`

**Files:**
- Create: `modules/media/vimeo-webinare/assets/css/statistiken.css`
- Create: `modules/media/vimeo-webinare/assets/js/statistiken.js`

- [ ] **Step 1: CSS anlegen**

`modules/media/vimeo-webinare/assets/css/statistiken.css`:

```css
/* Statistiken — KPI-Grid + Performance-Tabelle */

.dgptm-vw-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 16px;
}
.dgptm-vw-kpi {
    text-align: center;
    padding: 20px 12px;
}
.dgptm-vw-kpi-number {
    font-size: 36px;
    font-weight: 700;
    color: var(--dd-primary);
    line-height: 1.1;
    margin-bottom: 6px;
}
.dgptm-vw-kpi-label {
    font-size: 13px;
    color: var(--dd-muted);
}

/* Performance-Tabelle */
.dgptm-vw-performance h3 { margin-bottom: 12px; }
.dgptm-vw-stats-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 14px;
}
.dgptm-vw-stats-table th,
.dgptm-vw-stats-table td {
    padding: 10px 12px;
    text-align: left;
    border-bottom: 1px solid var(--dd-border);
}
.dgptm-vw-stats-table thead th {
    color: var(--dd-muted);
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .03em;
    cursor: pointer;
    user-select: none;
}
.dgptm-vw-stats-table thead th[data-sort]:hover { color: var(--dd-primary); }
.dgptm-vw-stats-table thead th.is-sorted-asc::after { content: ' ▲'; font-size: 10px; }
.dgptm-vw-stats-table thead th.is-sorted-desc::after { content: ' ▼'; font-size: 10px; }
.dgptm-vw-stats-table tbody tr:hover { background: var(--dd-primary-light); }
.dgptm-vw-stats-table tbody tr:last-child td { border-bottom: none; }

/* Sparkline */
.dgptm-vw-sparkline {
    width: 80px;
    height: 6px;
    background: var(--dd-primary-light);
    border-radius: 999px;
    position: relative;
    overflow: hidden;
}
.dgptm-vw-sparkline::after {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: calc(var(--rate, 0) * 1%);
    background: var(--dd-primary);
    transition: width .3s;
}

/* Mobile <768px — Tabelle zu Karten */
@media (max-width: 768px) {
    .dgptm-vw-kpi-grid { grid-template-columns: 1fr; }
    .dgptm-vw-stats-table thead { display: none; }
    .dgptm-vw-stats-table,
    .dgptm-vw-stats-table tbody,
    .dgptm-vw-stats-table tr,
    .dgptm-vw-stats-table td {
        display: block;
        width: 100%;
    }
    .dgptm-vw-stats-table tbody tr {
        background: var(--dd-card);
        border: 1px solid var(--dd-border);
        border-radius: var(--dd-radius);
        padding: 12px;
        margin-bottom: 12px;
    }
    .dgptm-vw-stats-table tbody tr:hover { background: var(--dd-card); }
    .dgptm-vw-stats-table td {
        padding: 4px 0;
        border: none;
        display: grid;
        grid-template-columns: 140px 1fr;
        gap: 8px;
    }
    .dgptm-vw-stats-table td::before {
        content: attr(data-label);
        color: var(--dd-muted);
        font-size: 13px;
    }
    .dgptm-vw-sparkline { width: 100%; }
}
```

- [ ] **Step 2: JS anlegen**

`modules/media/vimeo-webinare/assets/js/statistiken.js`:

```javascript
/* Statistiken — client-seitige Sortierung der Performance-Tabelle */
(function () {
    'use strict';

    function getSortValue($row, key) {
        var el = $row.get(0);
        if (!el) return null;
        if (key === 'title') return (el.dataset.title || '').toLowerCase();
        var raw = el.dataset[key];
        return raw !== undefined ? parseFloat(raw) : 0;
    }

    function sortBy(table, key, direction) {
        var $tbody = jQuery(table).find('tbody');
        var rows = $tbody.find('tr').toArray();
        rows.sort(function (a, b) {
            var va = getSortValue(jQuery(a), key);
            var vb = getSortValue(jQuery(b), key);
            if (va < vb) return direction === 'asc' ? -1 : 1;
            if (va > vb) return direction === 'asc' ? 1 : -1;
            return 0;
        });
        rows.forEach(function (r) { $tbody.append(r); });
    }

    jQuery(function ($) {
        $('.dgptm-vw-stats-table thead th[data-sort]').on('click', function () {
            var key = $(this).data('sort');
            var $th = $(this);
            var $table = $th.closest('table');
            var direction = $th.hasClass('is-sorted-asc') ? 'desc' : 'asc';
            $table.find('thead th').removeClass('is-sorted-asc is-sorted-desc');
            $th.addClass(direction === 'asc' ? 'is-sorted-asc' : 'is-sorted-desc');
            sortBy($table, key, direction);
        });
    });
})();
```

- [ ] **Step 3: Asset-Enqueue für Statistiken**

In `dgptm-vimeo-webinare.php` in der Methode `enqueue_assets()`, unterhalb des Manager-Blocks (siehe Task 3.6) ergänzen:

```php
        if (has_shortcode($content, 'vimeo_webinar_statistiken')) {
            wp_enqueue_style(
                'dgptm-vw-statistiken',
                plugin_dir_url(__FILE__) . 'assets/css/statistiken.css',
                ['dgptm-vw-dashboard-integration'],
                '2.0.0'
            );
            wp_enqueue_script(
                'dgptm-vw-statistiken',
                plugin_dir_url(__FILE__) . 'assets/js/statistiken.js',
                ['jquery'],
                '2.0.0',
                true
            );
        }
```

- [ ] **Step 4: Manueller Test**

1. Seite „Statistiken-Test" mit `[vimeo_webinar_statistiken]` anlegen.
2. Als eingeloggter Admin öffnen.
3. Erwartung: 4 KPI-Karten oben, Performance-Tabelle darunter mit Sparklines.
4. Header-Spalten klicken: Sortierung wechselt, Pfeil-Indikator erscheint.
5. DevTools → Mobile: KPI-Karten werden 1-spaltig, Tabelle wird zu Karten mit Data-Labels.

- [ ] **Step 5: Commit**

```bash
git add modules/media/vimeo-webinare/assets/ modules/media/vimeo-webinare/dgptm-vimeo-webinare.php
git commit -m "$(cat <<'EOF'
feat(vimeo-webinare): Statistiken CSS/JS + Asset-Enqueue

KPI-Grid 4-spaltig Desktop / 1-spaltig Mobile, Performance-Tabelle
clientseitig sortierbar, CSS-Sparkline pro Zeile.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Phase 5 — Frontend-Liste

### Task 5.1: Shortcode-Liste-Klasse

**Files:**
- Create: `modules/media/vimeo-webinare/includes/class-shortcode-liste.php`

- [ ] **Step 1: Klasse anlegen**

Inhalt:

```php
<?php
/**
 * Shortcode [vimeo_webinar_liste] — öffentlicher Frontend-Katalog.
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('DGPTM_VW_Shortcode_Liste')) {

    class DGPTM_VW_Shortcode_Liste {

        private static $instance = null;

        public static function get_instance() {
            if (null === self::$instance) self::$instance = new self();
            return self::$instance;
        }

        private function __construct() {
            add_shortcode('vimeo_webinar_liste', [$this, 'render']);
        }

        public function render($atts): string {
            $user_id = get_current_user_id();
            $is_logged_in = is_user_logged_in();

            $repo = DGPTM_VW_Webinar_Repository::get_instance();
            $webinars_raw = get_posts([
                'post_type'      => 'vimeo_webinar',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'orderby'        => 'title',
                'order'          => 'ASC',
            ]);

            // Fortschritt pro Webinar ermitteln
            $main = DGPTM_Vimeo_Webinare::get_instance();
            $cookie_progress = $is_logged_in ? [] : $main->get_all_cookie_progress();

            $history = [];
            if ($is_logged_in && $user_id) {
                $history = $main->get_user_webinar_history($user_id, 5);
            }

            ob_start();
            include plugin_dir_path(DGPTM_SUITE_FILE) . 'modules/media/vimeo-webinare/templates/liste.php';
            return ob_get_clean();
        }
    }
}
```

- [ ] **Step 2: Bestehende Shortcode-Registrierung entfernen**

In `dgptm-vimeo-webinare.php` Zeile 109 entfernen:
```php
        add_shortcode('vimeo_webinar_liste', [$this, 'webinar_liste_shortcode']);
```

- [ ] **Step 3: Klasse im Bootstrap requiren**

In `__construct()` ergänzen (nach Statistiken-Require):

```php
        require_once plugin_dir_path(__FILE__) . 'includes/class-shortcode-liste.php';
        DGPTM_VW_Shortcode_Liste::get_instance();
```

- [ ] **Step 4: Commit**

```bash
git add modules/media/vimeo-webinare/
git commit -m "$(cat <<'EOF'
feat(vimeo-webinare): Shortcode-Liste als eigene Klasse

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 5.2: Template `liste.php` im Dashboard-Look neu schreiben

**Files:**
- Modify (vollständig ersetzen): `modules/media/vimeo-webinare/templates/liste.php`

- [ ] **Step 1: Template komplett ersetzen**

Ersetze den gesamten Inhalt von `modules/media/vimeo-webinare/templates/liste.php` durch:

```php
<?php
/**
 * Template: Webinar-Liste (öffentlicher Frontend-Katalog).
 * Design an Mitglieder-Dashboard angeglichen.
 *
 * Variablen (vom Shortcode-Liste bereitgestellt):
 *   $webinars_raw : WP_Post[]
 *   $user_id      : int
 *   $is_logged_in : bool
 *   $cookie_progress : array — [webinar_id => ['progress'=>float, 'watched_time'=>float]]
 *   $history      : array — eingeloggt: letzte 5 Einträge
 */
if (!defined('ABSPATH')) exit;
?>
<div class="dgptm-vw dgptm-vw-liste">

    <?php if (!$is_logged_in): ?>
        <div class="dgptm-card dgptm-vw-login-banner">
            <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
            <div class="dgptm-vw-login-text">
                <strong>Hinweis:</strong> Sie sind nicht angemeldet. Fortschritte werden nur lokal gespeichert und nicht in Ihrer Fortbildungsliste eingetragen.
            </div>
            <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" class="dgptm-btn--primary">Jetzt anmelden</a>
        </div>
    <?php endif; ?>

    <?php if ($is_logged_in && !empty($history)): ?>
        <div class="dgptm-card dgptm-vw-history">
            <h3>
                <span class="dashicons dashicons-backup" aria-hidden="true"></span>
                Zuletzt angesehen
            </h3>
            <ul class="dgptm-vw-history-list">
                <?php foreach ($history as $item):
                    $date_str = '';
                    try {
                        $date = new DateTime($item['last_access']);
                        $now = new DateTime();
                        $diff = $now->diff($date);
                        if ($diff->days == 0)      $date_str = 'Heute';
                        elseif ($diff->days == 1)  $date_str = 'Gestern';
                        elseif ($diff->days < 7)   $date_str = 'vor ' . $diff->days . ' Tagen';
                        else                       $date_str = $date->format('d.m.Y');
                    } catch (\Exception $e) { /* ignore */ }
                    $url = home_url('/wissen/webinar/' . $item['webinar_id']);
                ?>
                <li class="dgptm-vw-history-item">
                    <a href="<?php echo esc_url($url); ?>" class="dgptm-vw-history-title"><?php echo esc_html($item['title']); ?></a>
                    <span class="dgptm-vw-history-date"><?php echo esc_html($date_str); ?></span>
                    <?php if (!empty($item['completed'])): ?>
                        <span class="dgptm-badge dgptm-badge--success">
                            <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                            Abgeschlossen
                        </span>
                    <?php else: ?>
                        <div class="dgptm-progress" aria-label="Fortschritt">
                            <div class="dgptm-progress-fill" style="width: <?php echo esc_attr($item['progress']); ?>%"></div>
                        </div>
                        <span class="dgptm-vw-history-percent"><?php echo esc_html(number_format($item['progress'], 0)); ?>%</span>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <h2 class="dgptm-vw-heading">
        <span class="dashicons dashicons-format-video" aria-hidden="true"></span>
        Verfügbare Webinare
    </h2>

    <?php if (empty($webinars_raw)): ?>
        <p class="dgptm-vw-empty">Derzeit sind keine Webinare verfügbar.</p>
    <?php else: ?>

        <div class="dgptm-vw-filter">
            <label class="dgptm-vw-search">
                <span class="dashicons dashicons-search" aria-hidden="true"></span>
                <input type="text" class="dgptm-vw-liste-search" placeholder="Webinare durchsuchen..." />
            </label>
            <select class="dgptm-vw-liste-status">
                <option value="all">Alle anzeigen</option>
                <option value="not-started">Noch nicht begonnen</option>
                <option value="in-progress">In Bearbeitung</option>
                <option value="completed">Abgeschlossen</option>
            </select>
        </div>

        <div class="dgptm-vw-grid">
            <?php foreach ($webinars_raw as $webinar):
                $webinar_id = $webinar->ID;
                $vimeo_id = get_field('vimeo_id', $webinar_id);
                $points = get_field('ebcp_points', $webinar_id) ?: 1;
                $completion_req = get_field('completion_percentage', $webinar_id) ?: 90;

                $progress = 0;
                $is_completed = false;
                $is_local_progress = false;

                if ($is_logged_in && $user_id) {
                    $progress = floatval(get_user_meta($user_id, '_vw_progress_' . $webinar_id, true));
                    $is_completed = (bool) get_user_meta($user_id, '_vw_completed_' . $webinar_id, true);
                } elseif (isset($cookie_progress[$webinar_id])) {
                    $progress = $cookie_progress[$webinar_id]['progress'] ?? 0;
                    $is_local_progress = true;
                }

                $status = 'not-started';
                $badge_class = 'dgptm-badge--muted';
                $status_label = 'Noch nicht begonnen';
                if ($is_completed) {
                    $status = 'completed';
                    $badge_class = 'dgptm-badge--success';
                    $status_label = 'Abgeschlossen';
                } elseif ($progress > 0) {
                    $status = 'in-progress';
                    $badge_class = 'dgptm-badge--accent';
                    $status_label = 'In Bearbeitung';
                }

                $thumbnail = $vimeo_id ? "https://vumbnail.com/{$vimeo_id}.jpg" : '';
            ?>
            <article class="dgptm-card dgptm-vw-webinar-card"
                     data-status="<?php echo esc_attr($status); ?>"
                     data-title="<?php echo esc_attr(strtolower($webinar->post_title)); ?>">

                <div class="dgptm-vw-thumb"<?php if ($thumbnail): ?> style="background-image:url('<?php echo esc_url($thumbnail); ?>');"<?php endif; ?>>
                    <?php if (!$thumbnail): ?>
                        <span class="dashicons dashicons-format-video dgptm-vw-thumb-fallback" aria-hidden="true"></span>
                    <?php endif; ?>
                    <span class="dgptm-badge <?php echo esc_attr($badge_class); ?> dgptm-vw-thumb-badge">
                        <?php if ($is_completed): ?>
                            <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                        <?php endif; ?>
                        <?php echo esc_html($status_label); ?>
                    </span>
                    <?php if ($is_local_progress): ?>
                        <span class="dgptm-vw-local-indicator" title="Nur lokal gespeichert" aria-label="Nur lokal gespeichert">
                            <span class="dashicons dashicons-smartphone" aria-hidden="true"></span>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="dgptm-vw-card-body">
                    <h3 class="dgptm-vw-card-title"><?php echo esc_html($webinar->post_title); ?></h3>

                    <div class="dgptm-vw-card-meta">
                        <span><span class="dashicons dashicons-star-filled"></span> <?php echo esc_html(number_format_i18n($points, 1)); ?> EBCP</span>
                        <span><span class="dashicons dashicons-clock"></span> <?php echo esc_html($completion_req); ?>% erf.</span>
                    </div>

                    <?php if ($progress > 0): ?>
                        <div class="dgptm-progress">
                            <div class="dgptm-progress-fill" style="width: <?php echo esc_attr($progress); ?>%"></div>
                        </div>
                        <div class="dgptm-vw-card-progress-text"><?php echo esc_html(number_format($progress, 0)); ?>%</div>
                    <?php endif; ?>

                    <p class="dgptm-vw-card-excerpt"><?php echo esc_html(wp_trim_words($webinar->post_content, 20)); ?></p>

                    <div class="dgptm-vw-card-actions">
                        <a href="<?php echo esc_url(home_url('/wissen/webinar/' . $webinar_id)); ?>" class="dgptm-btn--primary">
                            <?php
                            if ($is_completed) echo 'Erneut ansehen';
                            elseif ($progress > 0) echo 'Fortsetzen';
                            else echo 'Jetzt ansehen';
                            ?>
                        </a>
                        <?php if ($is_completed && $is_logged_in): ?>
                            <button type="button" class="dgptm-btn--ghost dgptm-vw-certificate" data-webinar-id="<?php echo esc_attr($webinar_id); ?>">
                                <span class="dashicons dashicons-awards" aria-hidden="true"></span>
                                Zertifikat
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>

</div>
```

- [ ] **Step 2: Commit**

```bash
git add modules/media/vimeo-webinare/templates/liste.php
git commit -m "$(cat <<'EOF'
feat(vimeo-webinare): Liste-Template im Dashboard-Look

Emojis durch Dashicons, Karten mit .dgptm-card, Badge-Varianten
(muted/accent/success), .dgptm-progress-Balken, Login-Banner als Card.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 5.3: `liste.css` komplett neu schreiben

**Files:**
- Modify (vollständig ersetzen): `modules/media/vimeo-webinare/assets/css/liste.css` **oder** neu anlegen falls die alte an anderer Stelle wohnt. Die Alt-Datei `assets/css/style.css` bleibt stehen für den Player/Alt-Pfade; sie wird in Task 6.1 aufgeräumt.

- [ ] **Step 1: Neue liste.css anlegen**

Inhalt `modules/media/vimeo-webinare/assets/css/liste.css`:

```css
/* Frontend-Liste — Dashboard-Look */

.dgptm-vw-liste { max-width: 1100px; margin: 0 auto; }

/* Login-Banner */
.dgptm-vw-login-banner {
    display: flex;
    align-items: center;
    gap: 12px;
    border-left: 3px solid var(--dd-accent);
}
.dgptm-vw-login-banner .dashicons-info-outline {
    color: var(--dd-accent);
    font-size: 20px;
}
.dgptm-vw-login-text { flex: 1; font-size: 14px; }

/* History */
.dgptm-vw-history h3 {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
    color: var(--dd-muted);
    font-weight: 600;
    margin-bottom: 12px;
}
.dgptm-vw-history-list {
    list-style: none;
    margin: 0;
    padding: 0;
}
.dgptm-vw-history-item {
    display: grid;
    grid-template-columns: 1fr auto auto;
    gap: 12px;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid var(--dd-border);
    font-size: 14px;
}
.dgptm-vw-history-item:last-child { border-bottom: none; }
.dgptm-vw-history-title {
    color: var(--dd-primary);
    text-decoration: none;
    font-weight: 500;
}
.dgptm-vw-history-title:hover { text-decoration: underline; }
.dgptm-vw-history-date { color: var(--dd-muted); font-size: 13px; }
.dgptm-vw-history-item .dgptm-progress { width: 100px; }
.dgptm-vw-history-percent { color: var(--dd-muted); font-size: 13px; min-width: 36px; text-align: right; }

/* Heading */
.dgptm-vw-heading {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 20px;
    margin: 24px 0 16px;
    color: var(--dd-text);
}

/* Filter */
.dgptm-vw-filter {
    display: flex;
    gap: 12px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}
.dgptm-vw-filter .dgptm-vw-search { flex: 1 1 240px; max-width: 360px; position: relative; }
.dgptm-vw-filter .dgptm-vw-search .dashicons {
    position: absolute; left: 10px; top: 50%; transform: translateY(-50%);
    color: var(--dd-muted); pointer-events: none;
}
.dgptm-vw-filter .dgptm-vw-search input { padding-left: 34px; }
.dgptm-vw-filter select { flex: 0 0 200px; }

/* Grid */
.dgptm-vw-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 16px;
}

/* Card */
.dgptm-vw-webinar-card {
    padding: 0;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

/* Thumb */
.dgptm-vw-thumb {
    position: relative;
    aspect-ratio: 16 / 9;
    background-color: var(--dd-primary-light);
    background-size: cover;
    background-position: center;
    display: flex;
    align-items: center;
    justify-content: center;
}
.dgptm-vw-thumb-fallback {
    color: var(--dd-primary);
    font-size: 48px;
    opacity: .5;
}
.dgptm-vw-thumb-badge {
    position: absolute;
    top: 10px;
    left: 10px;
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
}
.dgptm-vw-local-indicator {
    position: absolute;
    top: 10px;
    right: 10px;
    color: var(--dd-muted);
    background: rgba(255,255,255,.85);
    width: 24px; height: 24px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.dgptm-vw-local-indicator .dashicons { font-size: 14px; }

/* Card body */
.dgptm-vw-card-body { padding: 14px; display: flex; flex-direction: column; flex: 1; gap: 8px; }
.dgptm-vw-card-title { margin: 0 0 4px; font-size: 16px; }
.dgptm-vw-card-meta {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    font-size: 13px;
    color: var(--dd-muted);
}
.dgptm-vw-card-meta .dashicons { font-size: 14px; color: var(--dd-primary); }
.dgptm-vw-card-progress-text { font-size: 12px; color: var(--dd-muted); text-align: right; }
.dgptm-vw-card-excerpt { font-size: 13px; color: var(--dd-text); margin: 0; flex: 1; }

.dgptm-vw-card-actions {
    display: flex;
    gap: 8px;
    margin-top: auto;
}
.dgptm-vw-card-actions .dgptm-btn--primary { flex: 1; text-align: center; }
.dgptm-vw-card-actions .dashicons { vertical-align: middle; }

/* Empty */
.dgptm-vw-empty { text-align: center; color: var(--dd-muted); padding: 40px 0; }

/* Mobile */
@media (max-width: 600px) {
    .dgptm-vw-grid { grid-template-columns: 1fr; }
    .dgptm-vw-filter select { flex: 1 1 100%; }
    .dgptm-vw-card-actions { flex-direction: column; }
    .dgptm-vw-card-actions .dgptm-btn--primary,
    .dgptm-vw-card-actions .dgptm-btn--ghost { width: 100%; text-align: center; }
    .dgptm-vw-history-item {
        grid-template-columns: 1fr auto;
        row-gap: 4px;
    }
    .dgptm-vw-history-item .dgptm-progress { grid-column: 1 / -1; width: 100%; }
    .dgptm-vw-history-percent { grid-column: 2; }
}
```

- [ ] **Step 2: liste.js** (Filter + Such-Logik)

Prüfen, ob bereits eine `liste.js` existiert. Falls nicht:

`modules/media/vimeo-webinare/assets/js/liste.js`:

```javascript
/* Liste — Suche + Status-Filter */
(function ($) {
    'use strict';

    function applyFilters($root) {
        var q = ($root.find('.dgptm-vw-liste-search').val() || '').trim().toLowerCase();
        var status = $root.find('.dgptm-vw-liste-status').val() || 'all';

        $root.find('.dgptm-vw-webinar-card').each(function () {
            var t = ($(this).data('title') || '').toString();
            var s = $(this).data('status') || 'not-started';
            var matchSearch = !q || t.indexOf(q) !== -1;
            var matchStatus = status === 'all' || s === status;
            $(this).toggle(matchSearch && matchStatus);
        });
    }

    $(function () {
        var $root = $('.dgptm-vw-liste');
        if (!$root.length) return;

        $root.on('input', '.dgptm-vw-liste-search', function () { applyFilters($root); });
        $root.on('change', '.dgptm-vw-liste-status', function () { applyFilters($root); });

        // Zertifikat-Klick delegiert (falls nicht schon anderswo gebunden)
        $root.on('click', '.dgptm-vw-certificate', function () {
            var id = $(this).data('webinar-id');
            if (!id) return;
            var ajaxUrl = (window.dgptm_vw_ajax && window.dgptm_vw_ajax.url) || '/wp-admin/admin-ajax.php';
            var nonce = (window.dgptm_vw_ajax && window.dgptm_vw_ajax.cert_nonce) || '';
            $.post(ajaxUrl, { action: 'vw_generate_certificate', webinar_id: id, nonce: nonce })
             .then(function (resp) {
                 if (resp && resp.success && resp.data && resp.data.url) {
                     window.open(resp.data.url, '_blank');
                 } else {
                     window.alert((resp && resp.data) || 'Zertifikat konnte nicht erzeugt werden.');
                 }
             });
        });
    });
})(jQuery);
```

**Achtung:** Der Zertifikat-Nonce muss von Server-Seite via `wp_localize_script` bereitgestellt werden. Prüfe, ob `dgptm_vw_ajax`-Object schon in der alten `script.js`/`enqueue_assets` existiert. Falls ja: Namen übernehmen. Falls nein: ein neues `wp_localize_script` setzen. Für den ersten manuellen Test darf der Button ausbleiben — Task 6.1 räumt die Localize-Struktur sauber auf.

- [ ] **Step 3: Asset-Enqueue für Liste**

In `dgptm-vimeo-webinare.php` in der Methode `enqueue_assets()`:

```php
        if (has_shortcode($content, 'vimeo_webinar_liste')) {
            wp_enqueue_style(
                'dgptm-vw-liste',
                plugin_dir_url(__FILE__) . 'assets/css/liste.css',
                ['dgptm-vw-dashboard-integration'],
                '2.0.0'
            );
            wp_enqueue_script(
                'dgptm-vw-liste',
                plugin_dir_url(__FILE__) . 'assets/js/liste.js',
                ['jquery'],
                '2.0.0',
                true
            );
            wp_localize_script('dgptm-vw-liste', 'dgptm_vw_ajax', [
                'url' => admin_url('admin-ajax.php'),
                'cert_nonce' => wp_create_nonce('vw_generate_certificate'),
            ]);
        }
```

**Wichtig:** In der alten `ajax_generate_certificate`-Methode muss der Nonce `vw_generate_certificate` geprüft werden. Prüfe Zeile 913 (`ajax_generate_certificate`). Falls der Handler einen anderen Nonce erwartet, den Namen hier anpassen (oder den Handler nachziehen).

- [ ] **Step 4: Manueller Test**

1. Öffentliche Seite mit `[vimeo_webinar_liste]` aufrufen — einmal eingeloggt, einmal im Incognito.
2. Erwartung eingeloggt: Historie oben (falls Verlauf vorhanden), darunter Grid mit Karten im Dashboard-Look.
3. Erwartung nicht eingeloggt: Login-Banner oben, danach Grid. Fortschritt aus Cookies wird angezeigt (falls vorhanden).
4. Filter „In Bearbeitung" und „Abgeschlossen" blendet entsprechend. Suche filtert nach Titel.
5. Mobile <600 px: 1-spaltig, Buttons full-width.
6. Zertifikat-Button bei abgeschlossenen Webinaren (eingeloggt): Klick → PDF öffnet sich in neuem Tab.

- [ ] **Step 5: Commit**

```bash
git add modules/media/vimeo-webinare/assets/ modules/media/vimeo-webinare/dgptm-vimeo-webinare.php
git commit -m "$(cat <<'EOF'
feat(vimeo-webinare): Liste CSS/JS im Dashboard-Look + Asset-Enqueue

Karten-Grid mit 16:9-Thumbnails, Badge-Varianten (muted/accent/success),
.dgptm-progress-Balken, Dashicons statt Emojis. Nonce-Localize für
Zertifikat-Download.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Phase 6 — Migration, Cleanup, Versionierung

### Task 6.1: Alten Manager-Code aus `dgptm-vimeo-webinare.php` entfernen

**Files:**
- Modify: `modules/media/vimeo-webinare/dgptm-vimeo-webinare.php`
- Delete (ggf.): `modules/media/vimeo-webinare/templates/manager.php` (alte Modal-Variante)
- Delete (ggf.): `modules/media/vimeo-webinare/assets/css/admin-style.css`, `assets/js/admin-script.js` — nur wenn andere Admin-Subpages diese nicht mehr brauchen

- [ ] **Step 1: Alte Shortcode- und AJAX-Methoden entfernen**

In `dgptm-vimeo-webinare.php` folgende Methoden komplett löschen:
- `public function webinar_manager_shortcode($atts)` (ab Zeile ~700)
- `public function ajax_manager_create()` (ab Zeile ~1838)
- `public function ajax_manager_update()` (ab Zeile ~1878)
- `public function ajax_manager_delete()` (ab Zeile ~1915)
- `public function ajax_manager_stats()` (ab Zeile ~1940)
- `private function get_webinar_stats($webinar_id)` (ab Zeile ~1961) — wurde durch Repository ersetzt
- `public function webinar_liste_shortcode($atts)` (ab Zeile ~674) — falls noch vorhanden

Die `add_action`/`add_shortcode`-Registrierungen für diese Handler wurden bereits in Task 3.1 und 5.1 entfernt. Bei der Entfernung der Methoden darauf achten, dass keine Code-Pfade daneben (z. B. `render_admin_stats`) noch darauf zugreifen — falls doch: ebenfalls entfernen oder auf Repository umstellen.

- [ ] **Step 2: Altes `templates/manager.php` entfernen (Modal-Variante)**

```bash
git rm "modules/media/vimeo-webinare/templates/manager.php"
```

- [ ] **Step 3: Syntax-Check**

```bash
/d/php/php -l "modules/media/vimeo-webinare/dgptm-vimeo-webinare.php"
```

Expected: `No syntax errors detected`

- [ ] **Step 4: Smoke-Test — alles funktioniert noch**

1. Seiten mit `[vimeo_webinar_manager]`, `[vimeo_webinar_liste]`, `[vimeo_webinar_statistiken]` aufrufen.
2. Jeweils keine PHP-Fehler im `debug.log`.
3. Player unter `/wissen/webinar/{id}` funktioniert noch (hat eigene, nicht-manager-bezogene Pfade).
4. Admin-Subpages (Import, Einstellungen, Zertifikat-Designer) funktionieren weiterhin — sofern sie vorher funktioniert haben.

- [ ] **Step 5: Commit**

```bash
git add modules/media/vimeo-webinare/
git commit -m "$(cat <<'EOF'
refactor(vimeo-webinare): Alten Manager-Code entfernt

- webinar_manager_shortcode, webinar_liste_shortcode, ajax_manager_*
  und get_webinar_stats aus der Hauptdatei entfernt (durch Klassen
  in includes/class-shortcode-* ersetzt).
- templates/manager.php (Modal-Variante) entfernt.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 6.2: Version auf 2.0.0 heben

**Files:**
- Modify: `modules/media/vimeo-webinare/dgptm-vimeo-webinare.php` (Plugin-Header)
- Modify: `modules/media/vimeo-webinare/module.json`

- [ ] **Step 1: Plugin-Header aktualisieren**

In `dgptm-vimeo-webinare.php`, Zeile 6 ändern:

Alt:
```php
 * Version: 1.3.1
```

Neu:
```php
 * Version: 2.0.0
```

- [ ] **Step 2: module.json aktualisieren**

In `modules/media/vimeo-webinare/module.json`:

Alt:
```json
"version": "1.2.4",
```

Neu:
```json
"version": "2.0.0",
```

- [ ] **Step 3: Commit**

```bash
git add modules/media/vimeo-webinare/dgptm-vimeo-webinare.php modules/media/vimeo-webinare/module.json
git commit -m "$(cat <<'EOF'
chore(vimeo-webinare): Version auf 2.0.0 bumpen

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 6.3: Dashboard-Tab-Konfiguration dokumentieren

**Files:**
- Modify: `modules/media/vimeo-webinare/README.md`

- [ ] **Step 1: Setup-Abschnitt ergänzen**

In `modules/media/vimeo-webinare/README.md` am Ende anfügen:

```markdown
## Einrichtung im Mitglieder-Dashboard

Nach Aktivierung des Moduls einmalig konfigurieren:

1. WP-Admin → **Dashboard Config** (Submenü der DGPTM Plugin Suite).
2. Neuen **Top-Tab** anlegen:
   - Name: `Webinar-Verwaltung`
   - Permission-Dropdown: ACF-Feld `webinar` auswählen.
3. Unter dem Top-Tab zwei **Folder-Tabs** anlegen:
   - `Liste` — Content: `[vimeo_webinar_manager]`
   - `Statistiken` — Content: `[vimeo_webinar_statistiken]`
4. Für berechtigte User: im jeweiligen WP-Profil ACF-Feld „Webinare" auf **wahr**
   setzen. Erst danach wird der Tab im Dashboard angezeigt und die
   schreibenden AJAX-Endpoints akzeptieren die Requests.
```

- [ ] **Step 2: Commit**

```bash
git add modules/media/vimeo-webinare/README.md
git commit -m "$(cat <<'EOF'
docs(vimeo-webinare): Setup-Anleitung für Dashboard-Tabs

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Phase 7 — End-to-End-Smoketest

### Task 7.1: Komplettdurchlauf im Admin + Frontend

**Files:** — kein Code, nur manueller Test

- [ ] **Step 1: Dashboard-Tab-Config im WP-Admin**

1. WP-Admin → Dashboard Config.
2. Top-Tab „Webinar-Verwaltung" mit Permission `webinar` anlegen.
3. Child-Tab „Liste" mit Inhalt `[vimeo_webinar_manager]`.
4. Child-Tab „Statistiken" mit Inhalt `[vimeo_webinar_statistiken]`.
5. Speichern.

- [ ] **Step 2: User-Permission setzen**

1. WP-Admin → Benutzer → dein Profil bearbeiten.
2. ACF-Feld „Webinare" auf wahr setzen.
3. Speichern.

- [ ] **Step 3: Mitglieder-Dashboard aufrufen**

1. Seite mit `[dgptm_dashboard]` als eingeloggter Admin aufrufen.
2. Tab „Webinar-Verwaltung" sichtbar? Erwartung: ja.
3. Klick → Child-Tabs „Liste" und „Statistiken" zeigen sich.
4. „Liste" rendert Tabelle + funktionierendem Inline-Editor + Toast.
5. „Statistiken" rendert KPI-Karten + Performance-Tabelle mit Sortierung.

- [ ] **Step 4: Zweiter User ohne Permission**

1. Mit anderem User einloggen, der ACF-Feld `webinar = false` hat.
2. Mitglieder-Dashboard aufrufen.
3. Erwartung: Tab „Webinar-Verwaltung" fehlt.
4. Direkter AJAX-Call aus DevTools-Konsole:
   ```javascript
   jQuery.post('/wp-admin/admin-ajax.php', {
     action: 'dgptm_vw_save',
     nonce: 'beliebig',
     title: 'Hack',
     vimeo_id: '1',
     completion_percentage: 90,
     points: 1
   }).then(console.log);
   ```
5. Erwartung: `{success: false, data: 'Keine Berechtigung'}` (oder Nonce-Fehler).

- [ ] **Step 5: Öffentliche Liste**

1. Seite mit `[vimeo_webinar_liste]` im Incognito öffnen.
2. Login-Banner oben, Grid im Dashboard-Look.
3. Filter und Suche funktionieren.

- [ ] **Step 6: Player-Integration**

1. In der öffentlichen Liste ein Webinar öffnen (`/wissen/webinar/{id}`).
2. Video spielen, bis Fortschritt > 0.
3. Zurück zur Liste: Status-Badge jetzt „In Bearbeitung" (`--dd-accent`).
4. Bei Abschluss: `fortbildung`-Post existiert, Badge „Abgeschlossen", Zertifikat-Button funktioniert.

- [ ] **Step 7: Mobile Check**

1. DevTools → Gerät iPhone 12.
2. Manager-Tabelle → Karten, Inline-Editor zwischen Karten.
3. Statistiken: KPIs 1-spaltig, Performance-Tabelle zu Karten.
4. Liste: 1 Spalte, Buttons full-width.

- [ ] **Step 8: Cleanup-Commit (falls nötig)**

Falls beim Smoketest kleine Korrekturen nötig waren, sammle sie in einem abschließenden Commit:

```bash
git add -A
git commit -m "$(cat <<'EOF'
fix(vimeo-webinare): Feinkorrekturen nach Smoketest

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

Wenn keine Korrekturen nötig waren, diesen Schritt überspringen.

---

## Self-Review-Ergebnisse

Bei der Self-Review habe ich folgende Punkte geprüft:

- **Spec-Coverage:** Alle 10 Entscheidungen aus der Spec-Tabelle sind durch Tasks abgedeckt (1→0.1+1.1+ff, 2→0.1+6.2, 3→1.2, 4→3.3/3.5, 5→3.1+4.1+5.1, 6→2.1+5.2/5.3, 7→3.4+4.3+5.3, 8→1.1 `trash()`, 9→1.1 `get_average_completion_rate()`+4.1, 10→1.1+3.1+4.1+5.1).
- **Placeholder-Scan:** Keine „TBD", keine „implementiere später". Manuelle Testschritte sind explizit, Akzeptanzkriterien stehen im Text.
- **Typkonsistenz:** Die AJAX-Actions `dgptm_vw_save` / `dgptm_vw_delete` / `dgptm_vw_get_row` sind zwischen `class-shortcode-manager.php` und `manager.js` konsistent. Der Nonce-Action-String `dgptm_vw_manager` steht einmal in der Klassen-Konstante `NONCE_ACTION` und wird im Template als `$nonce` per `wp_create_nonce(self::NONCE_ACTION)` erzeugt.
- **Abhängigkeiten:** Task 3.1 required Repository aus 1.1; Task 3.2 required Template-Variablen aus 3.1; Task 3.5 required Template-DOM-Struktur aus 3.2. Reihenfolge ist linear.
- **Bekannte Abweichungen vom Spec-Text:** Der Spec nennt als Helper-Prozess „Render-Template übergibt vorbereitete Arrays" — Task 5.1 liefert zusätzlich `$webinars_raw` als `WP_Post[]`, weil das Liste-Template (anders als Manager/Stats) tiefere ACF-Feldzugriffe und User-Meta pro Karte macht. Das ist ein bewusster Kompromiss: den Liste-Pfad komplett über das Repository zu führen, hätte einen weiteren Helper `get_for_user($user_id)` erfordert — YAGNI-mäßig jetzt nicht nötig, solange das Template funktioniert.

---

**Plan complete and saved to `docs/superpowers/plans/2026-04-20-vimeo-webinar-manager-dashboard.md`. Two execution options:**

**1. Subagent-Driven (recommended)** — Ich dispatche einen frischen Subagent pro Task, review zwischen Tasks, schnelle Iteration.

**2. Inline Execution** — Ich führe die Tasks in dieser Session aus, Batch mit Checkpoints zum Review.

**Welcher Ansatz?**
