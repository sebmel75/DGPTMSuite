# Umfragen: Stats-Rendering + Datenschutz-Flag — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Korrekte Aggregation & Darstellung für Auswahl-Fragen mit Text-/Zahl-Anhängen; neues `is_privacy_sensitive`-Flag filtert Fragen aus der öffentlichen Ergebnisseite.

**Architecture:** Neue DB-Spalte via `ensure_columns`-Pattern. Zentrale Helper-Methode `DGPTM_Survey_Admin::render_question_result()` kapselt das komplette Switch-Statement der Antwort-Typen; Admin- und Public-Template delegieren dorthin. Public-Template filtert Fragen per SQL; Admin-Template zeigt alle mit Banner. Freitexte und Zahlenreihen im Expand-Panel via nativem `<details>/<summary>` (kein JS, kein Chart-Library).

**Tech Stack:** WordPress Plugin, PHP 7.4+, wpdb, jQuery (nur Editor-Save-Paths), natives HTML `<details>`/`<summary>` für Expand, reines CSS für Styling.

**Testing:** Kein Test-Framework im Projekt (siehe DGPTMSuite/CLAUDE.md). Jede Task hat manuelle Verifikation — Prüfschritte im Admin-Dashboard / Datenbank.

---

## Task 1: DB-Schema-Migration für `is_privacy_sensitive`

**Files:**
- Modify: `modules/business/umfragen/includes/class-survey-installer.php`
- Modify: `modules/business/umfragen/dgptm-umfragen.php`

- [ ] **Step 1: Schema-Definition in `install()` erweitern**

In `class-survey-installer.php` im SQL für `$table_questions` (nach Zeile 61) die neue Spalte einfügen — direkt nach `parent_answer_value`:

```php
            parent_answer_value VARCHAR(255) NOT NULL DEFAULT '',
            is_privacy_sensitive TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
```

- [ ] **Step 2: ALTER-Fallback in `ensure_columns()` ergänzen**

In `class-survey-installer.php` vor der `// Backfill`-Zeile (am Ende von `ensure_columns()`) ergänzen:

```php
        if (!$has($questions, 'is_privacy_sensitive')) {
            $wpdb->query("ALTER TABLE $questions ADD COLUMN is_privacy_sensitive TINYINT(1) NOT NULL DEFAULT 0");
        }
```

- [ ] **Step 3: Version-Konstante bumpen**

In `dgptm-umfragen.php` die Konstante von `1.6.0` auf `1.7.0`:

```php
if (!defined('DGPTM_UMFRAGEN_VERSION')) {
    define('DGPTM_UMFRAGEN_VERSION', '1.7.0');
}
```

`ensure_tables()` vergleicht DB-Version mit Konstante → Version-Mismatch triggert `install()`, das wiederum `ensure_columns()` aufruft, das die Spalte hinzufügt.

- [ ] **Step 4: Manuelles Testen**

1. Plugin-Suite neu laden (WP-Admin aufrufen)
2. In DB prüfen:
   ```sql
   SHOW COLUMNS FROM wp_dgptm_survey_questions LIKE 'is_privacy_sensitive';
   ```
   Erwartet: eine Zeile mit `TINYINT(1) NOT NULL DEFAULT 0`
3. Prüfen dass Option `dgptm_umfragen_db_version` auf `1.7.0` steht:
   ```sql
   SELECT option_value FROM wp_options WHERE option_name = 'dgptm_umfragen_db_version';
   ```

- [ ] **Step 5: Commit**

```bash
git add modules/business/umfragen/includes/class-survey-installer.php modules/business/umfragen/dgptm-umfragen.php
git commit -m "feat(umfragen): DB-Spalte is_privacy_sensitive + v1.7.0"
```

---

## Task 2: Backend-Persistenz des Flags

**Files:**
- Modify: `modules/business/umfragen/includes/class-survey-admin.php`

- [ ] **Step 1: `ajax_save_questions()` erweitern**

In `class-survey-admin.php` ungefähr Zeile 392-396 (innerhalb des `$data`-Arrays in `ajax_save_questions`) direkt nach `parent_answer_value` ergänzen:

```php
                'parent_answer_value' => sanitize_text_field($q['parent_answer_value'] ?? ''),
                'is_privacy_sensitive' => absint($q['is_privacy_sensitive'] ?? 0),
```

- [ ] **Step 2: `ajax_duplicate_survey()` — Flag mitkopieren**

Im Block um Zeile 544-548 (innerhalb des Arrays, das beim Duplizieren pro Frage erstellt wird) direkt nach `parent_answer_value` ergänzen:

```php
                'parent_answer_value' => $q->parent_answer_value,
                'is_privacy_sensitive' => $q->is_privacy_sensitive,
```

- [ ] **Step 3: Manuelles Testen (noch ohne UI — direkte DB-Manipulation)**

1. Für eine existierende Frage per SQL das Flag setzen:
   ```sql
   UPDATE wp_dgptm_survey_questions SET is_privacy_sensitive = 1 WHERE id = <test-id>;
   ```
2. Umfrage im Editor öffnen, minimal anpassen (z.B. einen Fragen-Text), speichern.
3. DB erneut prüfen: Flag soll noch immer `1` sein (es wurde nicht überschrieben, weil die UI den Wert beim Speichern mitschickt — mit `absint(null)` würde es auf 0 zurückfallen). Falls es auf `0` fällt, ist das erwartet und wird in Task 3/4 behoben.
4. Umfrage duplizieren → neue Umfrage hat Frage mit `is_privacy_sensitive = 1`.

- [ ] **Step 4: Commit**

```bash
git add modules/business/umfragen/includes/class-survey-admin.php
git commit -m "feat(umfragen): is_privacy_sensitive in save+duplicate übernehmen"
```

---

## Task 3: Frontend-Editor UI + JS (Checkbox "Datenschutzrelevant")

**Files:**
- Modify: `modules/business/umfragen/templates/frontend-editor.php`
- Modify: `modules/business/umfragen/assets/js/frontend-editor.js`

- [ ] **Step 1: Checkbox im Fragen-Render (bestehende Frage) hinzufügen**

In `templates/frontend-editor.php` um Zeile 378-381 (direkt nach der Pflichtfeld-Checkbox der existierenden Frage) ergänzen:

```php
                        <label class="dgptm-fe-checkbox">
                            <input type="checkbox" class="dgptm-fe-q-required" <?php checked($q->is_required); ?>>
                            Pflichtfeld
                        </label>
                        <label class="dgptm-fe-checkbox">
                            <input type="checkbox" class="dgptm-fe-q-privacy" <?php checked($q->is_privacy_sensitive); ?>>
                            🔒 Datenschutzrelevant
                        </label>
```

- [ ] **Step 2: Checkbox im Fragen-Template (neue Frage) hinzufügen**

In `templates/frontend-editor.php` um Zeile 505-508 (im Template-Block für neue Fragen) ergänzen:

```php
                <label class="dgptm-fe-checkbox">
                    <input type="checkbox" class="dgptm-fe-q-required">
                    Pflichtfeld
                </label>
                <label class="dgptm-fe-checkbox">
                    <input type="checkbox" class="dgptm-fe-q-privacy">
                    🔒 Datenschutzrelevant
                </label>
```

- [ ] **Step 3: JS-Save-Logik ergänzen**

In `assets/js/frontend-editor.js` um Zeile 545-553 (im Objekt, das pro Frage gebaut wird) nach `is_required:` ergänzen:

```javascript
                    is_required: $item.find('.dgptm-fe-q-required').is(':checked') ? 1 : 0,
                    is_privacy_sensitive: $item.find('.dgptm-fe-q-privacy').is(':checked') ? 1 : 0,
```

- [ ] **Step 4: Manuelles Testen**

1. Im Frontend-Editor eine neue Frage anlegen, "🔒 Datenschutzrelevant" ankreuzen, speichern.
2. In DB prüfen: `SELECT id, question_text, is_privacy_sensitive FROM wp_dgptm_survey_questions WHERE survey_id = <id>;` → Flag ist `1`.
3. Seite neu laden → Checkbox ist noch angekreuzt.
4. Flag wieder abwählen → speichern → DB Flag = `0`.

- [ ] **Step 5: Commit**

```bash
git add modules/business/umfragen/templates/frontend-editor.php modules/business/umfragen/assets/js/frontend-editor.js
git commit -m "feat(umfragen): Datenschutz-Checkbox im Frontend-Editor"
```

---

## Task 4: Admin-Editor UI + JS (Checkbox "Datenschutzrelevant")

**Files:**
- Modify: `modules/business/umfragen/templates/admin-survey-edit.php`
- Modify: `modules/business/umfragen/assets/js/admin.js`

- [ ] **Step 1: Checkbox im bestehenden Fragen-Block ergänzen**

In `templates/admin-survey-edit.php` um Zeile 377-380 (innerhalb der `<td>` der "Optionen"-Zeile, direkt nach dem Pflichtfeld-Label):

```php
                                    <label>
                                        <input type="checkbox" class="dgptm-q-required" <?php checked($q->is_required); ?>>
                                        Pflichtfeld
                                    </label>
                                    <br>
                                    <label>
                                        <input type="checkbox" class="dgptm-q-privacy" <?php checked($q->is_privacy_sensitive); ?>>
                                        🔒 Datenschutzrelevant
                                    </label>
```

- [ ] **Step 2: Checkbox im Neue-Frage-Template ergänzen**

Um Zeile 538-542 (zweites Pflichtfeld-Template für neue Fragen) analog:

```php
                        <td>
                            <label>
                                <input type="checkbox" class="dgptm-q-required">
                                Pflichtfeld
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" class="dgptm-q-privacy">
                                🔒 Datenschutzrelevant
                            </label>
                        </td>
```

- [ ] **Step 3: JS-Save-Logik**

In `assets/js/admin.js` suche nach `is_required:` (der Stelle, wo pro Frage ein Objekt gebaut wird). Dort direkt nach der `is_required`-Zeile ergänzen:

```javascript
is_privacy_sensitive: $item.find('.dgptm-q-privacy').is(':checked') ? 1 : 0,
```

Falls in `admin.js` mehrere Stellen existieren (Hauptsammlung + evtl. Validation-Hash), ergänze überall analog zum bestehenden `is_required`-Pattern.

- [ ] **Step 4: Manuelles Testen**

Analog zu Task 3, aber via WP-Admin → DGPTM Suite → Umfragen → Bearbeiten.

- [ ] **Step 5: Commit**

```bash
git add modules/business/umfragen/templates/admin-survey-edit.php modules/business/umfragen/assets/js/admin.js
git commit -m "feat(umfragen): Datenschutz-Checkbox im Admin-Editor"
```

---

## Task 5: Badge "🔒 Datenschutzrelevant" in den Fragen-Listen

**Files:**
- Modify: `modules/business/umfragen/templates/admin-survey-edit.php`
- Modify: `modules/business/umfragen/templates/frontend-editor.php`
- Modify: `modules/business/umfragen/assets/css/admin.css`
- Modify: `modules/business/umfragen/assets/css/frontend-editor.css`

- [ ] **Step 1: Admin-Liste-Badge**

In `admin-survey-edit.php` um Zeile 180-182 (direkt nach dem Pflicht-Badge in der kollabierten Fragen-Zeile):

```php
                        <?php if ($q->is_required) : ?>
                            <span class="dgptm-required-badge">Pflicht</span>
                        <?php endif; ?>
                        <?php if (!empty($q->is_privacy_sensitive)) : ?>
                            <span class="dgptm-privacy-badge">🔒 Datenschutzrelevant</span>
                        <?php endif; ?>
```

- [ ] **Step 2: Frontend-Editor-Liste-Badge**

In `templates/frontend-editor.php` um Zeile 195 (nach dem Pflicht-Badge im `dgptm-fe-question-header`) ergänzen:

```php
                        <?php if ($q->is_required) : ?><span class="dgptm-fe-required-badge">Pflicht</span><?php endif; ?>
                        <?php if (!empty($q->is_privacy_sensitive)) : ?><span class="dgptm-fe-privacy-badge">🔒 Datenschutzrelevant</span><?php endif; ?>
```

- [ ] **Step 3: CSS Admin**

In `assets/css/admin.css` am Ende ergänzen:

```css
.dgptm-privacy-badge {
    display: inline-block;
    padding: 2px 8px;
    margin-left: 6px;
    font-size: 11px;
    font-weight: 600;
    background: #fde7e9;
    color: #b32d2e;
    border-radius: 3px;
    border: 1px solid #f1b5b7;
}
```

- [ ] **Step 4: CSS Frontend-Editor**

In `assets/css/frontend-editor.css` am Ende ergänzen:

```css
.dgptm-fe-privacy-badge {
    display: inline-block;
    padding: 2px 8px;
    margin-left: 6px;
    font-size: 11px;
    font-weight: 600;
    background: #fde7e9;
    color: #b32d2e;
    border-radius: 3px;
    border: 1px solid #f1b5b7;
}
```

- [ ] **Step 5: Manuelles Testen**

1. Eine Frage als datenschutzrelevant markieren.
2. In beiden Editoren die Fragen-Liste öffnen → roter Badge "🔒 Datenschutzrelevant" erscheint neben der Frage.
3. Flag entfernen → Badge verschwindet nach Reload.

- [ ] **Step 6: Commit**

```bash
git add modules/business/umfragen/templates/admin-survey-edit.php modules/business/umfragen/templates/frontend-editor.php modules/business/umfragen/assets/css/admin.css modules/business/umfragen/assets/css/frontend-editor.css
git commit -m "feat(umfragen): Datenschutz-Badge in Fragen-Listen"
```

---

## Task 6: Helper-Methode `render_question_result()` mit neuer Parser-Logik

**Files:**
- Modify: `modules/business/umfragen/includes/class-survey-admin.php`

- [ ] **Step 1: Aggregations-Helper und Renderer als neue Methode ergänzen**

Am Ende der Klasse `DGPTM_Survey_Admin` (vor dem schließenden `}`) ergänzen:

```php
    /**
     * Aggregate answers for a single question into per-choice counts, numbers, and texts.
     * Handles the "Choice|||Text|||Number" storage format from radio/checkbox with choices_with_text/number.
     *
     * @return array<string, array{count:int, numbers:float[], texts:string[]}>
     */
    public static function aggregate_choice_answers($question, $q_answers) {
        $result = [];

        $init_choice = function ($label) use (&$result) {
            if (!isset($result[$label])) {
                $result[$label] = ['count' => 0, 'numbers' => [], 'texts' => []];
            }
        };

        $parse_value = function ($raw) {
            // Format: "base|||text|||number" — alle Felder optional nach base
            $parts = explode('|||', $raw, 3);
            return [
                'base'   => $parts[0] ?? '',
                'text'   => $parts[1] ?? '',
                'number' => $parts[2] ?? '',
            ];
        };

        $choices = $question->choices ? json_decode($question->choices, true) : [];
        if (is_array($choices)) {
            foreach ($choices as $c) {
                $init_choice($c);
            }
        }

        foreach ($q_answers as $a) {
            $raw = $a->answer_value;

            if ($question->question_type === 'checkbox') {
                $vals = json_decode($raw, true);
                if (!is_array($vals)) continue;
            } else {
                // radio, select — single string
                $vals = [$raw];
            }

            foreach ($vals as $val) {
                $parsed = $parse_value($val);
                $label = $parsed['base'] === '__other__' ? 'Sonstiges' : $parsed['base'];
                if ($label === '') continue;
                $init_choice($label);
                $result[$label]['count']++;
                if ($parsed['number'] !== '' && is_numeric($parsed['number'])) {
                    $result[$label]['numbers'][] = floatval($parsed['number']);
                }
                if ($parsed['text'] !== '') {
                    $result[$label]['texts'][] = $parsed['text'];
                }
            }
        }

        return $result;
    }

    /**
     * Render aggregated results for a single question.
     * Used by both admin-survey-results.php and public-results.php.
     * Echoes HTML directly.
     */
    public static function render_question_result($q, $q_answers) {
        $choices = $q->choices ? json_decode($q->choices, true) : [];

        switch ($q->question_type) {
            case 'radio':
            case 'select':
            case 'checkbox':
                $per_choice = self::aggregate_choice_answers($q, $q_answers);
                $total = count($q_answers);
                $max_count = 1;
                foreach ($per_choice as $data) {
                    if ($data['count'] > $max_count) $max_count = $data['count'];
                }
                echo '<div class="dgptm-bar-chart">';
                foreach ($per_choice as $label => $data) {
                    $pct = $max_count > 0 ? round(($data['count'] / $max_count) * 100) : 0;
                    $pct_total = $total > 0 ? round(($data['count'] / $total) * 100) : 0;
                    echo '<div class="dgptm-bar-row">';
                    echo '<span class="dgptm-bar-label" title="' . esc_attr($label) . '">' . esc_html($label) . '</span>';
                    echo '<div class="dgptm-bar-track"><div class="dgptm-bar-fill" style="width:' . esc_attr($pct) . '%"></div></div>';
                    echo '<span class="dgptm-bar-count">' . esc_html($data['count']) . ' (' . esc_html($pct_total) . '%)</span>';
                    echo '</div>';

                    if (!empty($data['numbers'])) {
                        $nums = $data['numbers'];
                        sort($nums);
                        $n = count($nums);
                        $sum = array_sum($nums);
                        $avg = $sum / $n;
                        $median_idx = (int) floor($n / 2);
                        $median = $n % 2 === 0 ? ($nums[$median_idx - 1] + $nums[$median_idx]) / 2 : $nums[$median_idx];
                        echo '<div class="dgptm-choice-stats">';
                        echo 'n=' . esc_html($n);
                        echo '  Median=' . esc_html(number_format_i18n($median, 1));
                        echo '  &Oslash;=' . esc_html(number_format_i18n($avg, 1));
                        echo '  Min&ndash;Max: ' . esc_html(number_format_i18n(min($nums))) . '&ndash;' . esc_html(number_format_i18n(max($nums)));
                        echo '  &Sigma;=' . esc_html(number_format_i18n($sum));
                        echo '</div>';
                    }

                    if (!empty($data['texts'])) {
                        echo '<details class="dgptm-choice-details">';
                        echo '<summary>' . count($data['texts']) . ' Freitext-Eintrag/Einträge</summary>';
                        echo '<ul class="dgptm-choice-texts">';
                        foreach ($data['texts'] as $t) {
                            echo '<li>' . esc_html($t) . '</li>';
                        }
                        echo '</ul>';
                        echo '</details>';
                    }

                    if (!empty($data['numbers']) && count($data['numbers']) > 0) {
                        $nums_display = $data['numbers'];
                        sort($nums_display);
                        echo '<details class="dgptm-choice-details">';
                        echo '<summary>Zahlen-Einzelwerte anzeigen</summary>';
                        echo '<p class="dgptm-choice-numbers">' . esc_html(implode(', ', array_map('number_format_i18n', $nums_display))) . '</p>';
                        echo '</details>';
                    }
                }
                echo '</div>';
                break;

            case 'number':
                $values = [];
                foreach ($q_answers as $a) {
                    if (is_numeric($a->answer_value)) $values[] = floatval($a->answer_value);
                }
                if (!empty($values)) {
                    sort($values);
                    $sum = array_sum($values);
                    $cnt = count($values);
                    $avg = $sum / $cnt;
                    $median_idx = (int) floor($cnt / 2);
                    $median = $cnt % 2 === 0 ? ($values[$median_idx - 1] + $values[$median_idx]) / 2 : $values[$median_idx];
                    echo '<div class="dgptm-number-stats">';
                    printf('<div class="dgptm-number-stat"><strong>%s</strong><span>Minimum</span></div>', esc_html(number_format_i18n(min($values))));
                    printf('<div class="dgptm-number-stat"><strong>%s</strong><span>Maximum</span></div>', esc_html(number_format_i18n(max($values))));
                    printf('<div class="dgptm-number-stat"><strong>%s</strong><span>Durchschnitt</span></div>', esc_html(number_format_i18n($avg, 1)));
                    printf('<div class="dgptm-number-stat"><strong>%s</strong><span>Median</span></div>', esc_html(number_format_i18n($median, 1)));
                    printf('<div class="dgptm-number-stat"><strong>%s</strong><span>Summe</span></div>', esc_html(number_format_i18n($sum)));
                    echo '</div>';
                } else {
                    echo '<p style="color:#999;">Keine numerischen Antworten.</p>';
                }
                break;

            case 'matrix':
                $rows = isset($choices['rows']) ? $choices['rows'] : [];
                $cols = isset($choices['columns']) ? $choices['columns'] : [];
                $matrix_counts = [];
                foreach ($rows as $r) {
                    $rk = sanitize_title($r);
                    foreach ($cols as $c) $matrix_counts[$rk][$c] = 0;
                }
                foreach ($q_answers as $a) {
                    $vals = json_decode($a->answer_value, true);
                    if (!is_array($vals)) continue;
                    foreach ($vals as $rk => $cv) {
                        if (isset($matrix_counts[$rk][$cv])) $matrix_counts[$rk][$cv]++;
                    }
                }
                $matrix_max = 1;
                foreach ($matrix_counts as $rc) foreach ($rc as $c) if ($c > $matrix_max) $matrix_max = $c;
                echo '<div class="dgptm-matrix-result"><table><thead><tr><th></th>';
                foreach ($cols as $c) echo '<th>' . esc_html($c) . '</th>';
                echo '</tr></thead><tbody>';
                foreach ($rows as $r) {
                    $rk = sanitize_title($r);
                    echo '<tr><th>' . esc_html($r) . '</th>';
                    foreach ($cols as $c) {
                        $v = $matrix_counts[$rk][$c] ?? 0;
                        $intensity = $matrix_max > 0 ? ($v / $matrix_max) * 0.4 : 0;
                        echo '<td class="dgptm-matrix-cell" style="background:rgba(34,113,177,' . $intensity . ')">' . esc_html($v) . '</td>';
                    }
                    echo '</tr>';
                }
                echo '</tbody></table></div>';
                break;

            case 'text':
            case 'textarea':
                echo '<div class="dgptm-text-responses">';
                if (empty($q_answers)) {
                    echo '<p style="color:#999;">Keine Antworten.</p>';
                } else {
                    foreach ($q_answers as $a) {
                        echo '<div class="dgptm-text-response-item">' . esc_html($a->answer_value) . '</div>';
                    }
                }
                echo '</div>';
                break;

            case 'file':
                $file_count = 0;
                foreach ($q_answers as $a) {
                    if ($a->file_ids) {
                        $fids = json_decode($a->file_ids, true);
                        if (is_array($fids)) $file_count += count($fids);
                    }
                }
                echo '<p>' . esc_html($file_count) . ' Datei(en) hochgeladen.</p>';
                break;
        }
    }
```

- [ ] **Step 2: Manuelles Testen (Helper isoliert)**

Kurz die Methode-Signatur und Parser-Korrektheit prüfen — Helper wird in den folgenden Tasks verdrahtet. Syntax-Check (PHP-lint):

```bash
/d/php/php -l modules/business/umfragen/includes/class-survey-admin.php
```

Erwartet: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add modules/business/umfragen/includes/class-survey-admin.php
git commit -m "feat(umfragen): render_question_result Helper + Choice-Parser"
```

---

## Task 7: Admin-Results-Template auf Helper umstellen + Datenschutz-Banner

**Files:**
- Modify: `modules/business/umfragen/templates/admin-survey-results.php`
- Modify: `modules/business/umfragen/assets/css/admin.css`

- [ ] **Step 1: Switch-Block durch Helper-Aufruf ersetzen**

In `templates/admin-survey-results.php` den gesamten `switch ($q->question_type):`-Block (Zeilen 182-387, einschließlich der `endswitch;`-Zeile) ersetzen durch:

```php
                    <?php if (!empty($q->is_privacy_sensitive)) : ?>
                        <div class="dgptm-privacy-banner">
                            🔒 Datenschutzrelevant — in öffentlicher Ansicht ausgeblendet
                        </div>
                    <?php endif; ?>

                    <?php DGPTM_Survey_Admin::render_question_result($q, $q_answers); ?>
```

- [ ] **Step 2: CSS für Banner + neue Stats-Zeile ergänzen**

In `assets/css/admin.css` am Ende ergänzen:

```css
.dgptm-privacy-banner {
    background: #fde7e9;
    color: #b32d2e;
    padding: 8px 12px;
    border-left: 3px solid #b32d2e;
    margin: 8px 0 12px;
    font-size: 13px;
}

.dgptm-choice-stats {
    font-size: 12px;
    color: #555;
    margin: 4px 0 8px 24px;
    font-family: monospace;
}

.dgptm-choice-details {
    margin: 4px 0 12px 24px;
    font-size: 13px;
}

.dgptm-choice-details > summary {
    cursor: pointer;
    color: #2271b1;
    padding: 2px 0;
}

.dgptm-choice-texts {
    margin: 6px 0 0 12px;
    padding-left: 12px;
}

.dgptm-choice-texts li {
    margin: 2px 0;
}

.dgptm-choice-numbers {
    margin: 6px 0 0 12px;
    font-family: monospace;
    color: #555;
}
```

- [ ] **Step 3: Manuelles Testen**

1. Umfrage mit Radio-Frage + `choices_with_text` anlegen (z.B. Frage "Wie hast du uns gefunden?" mit Option "Sonstiges: ___").
2. Zwei Test-Antworten abgeben, eine davon mit Freitext bei "Sonstiges".
3. Admin → Ergebnisse öffnen.
4. Erwartet:
   - Choice "Sonstiges" zeigt Balken mit korrekter Zählung (nicht mehr fragmentiert pro Freitext-String).
   - Unter dem Balken: `<details>`-Block "1 Freitext-Eintrag/Einträge" — aufklappbar zeigt den Text.
5. Eine Radio-Frage mit `choices_with_number` testen (z.B. "Wie viele Geräte?" mit Option "E-Gitarre [Anzahl]").
6. Erwartet: Stats-Zeile `n=X  Median=Y  Ø=Z  Min–Max: …  Σ=…` unter dem Balken; `<details>` "Zahlen-Einzelwerte anzeigen" listet sortiert auf.
7. Bei einer Frage Flag "Datenschutzrelevant" setzen → Ergebnis-Seite zeigt rotes Banner über dieser Frage.

- [ ] **Step 4: Commit**

```bash
git add modules/business/umfragen/templates/admin-survey-results.php modules/business/umfragen/assets/css/admin.css
git commit -m "feat(umfragen): Admin-Results nutzt Helper + Datenschutz-Banner"
```

---

## Task 8: Public-Results-Template: Filter + Helper + CSS

**Files:**
- Modify: `modules/business/umfragen/templates/public-results.php`
- Modify: `modules/business/umfragen/assets/css/frontend.css`

- [ ] **Step 1: Fragen-Query um Filter ergänzen**

In `templates/public-results.php` finde die Stelle, an der Fragen geladen werden (Suche nach `dgptm_survey_questions` oder `get_questions`). Der bestehende Query lautet typischerweise:

```php
$questions = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}dgptm_survey_questions WHERE survey_id = %d ORDER BY sort_order ASC",
    $survey->id
));
```

Ersetzen durch:

```php
$questions = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}dgptm_survey_questions
     WHERE survey_id = %d AND is_privacy_sensitive = 0
     ORDER BY sort_order ASC",
    $survey->id
));
```

- [ ] **Step 2: Render-Switch durch Helper-Aufruf ersetzen**

Den gesamten Switch-Block in `public-results.php` (gleiche Struktur wie im Admin-Template) ersetzen durch:

```php
<?php DGPTM_Survey_Admin::render_question_result($q, $q_answers); ?>
```

- [ ] **Step 3: CSS für Stats-Zeile + Details in Public-Frontend**

In `assets/css/frontend.css` am Ende dieselben Regeln ergänzen wie in Task 7 Schritt 2, aber **ohne** `.dgptm-privacy-banner` (gibt's auf Public nicht):

```css
.dgptm-choice-stats {
    font-size: 12px;
    color: #555;
    margin: 4px 0 8px 24px;
    font-family: monospace;
}

.dgptm-choice-details {
    margin: 4px 0 12px 24px;
    font-size: 13px;
}

.dgptm-choice-details > summary {
    cursor: pointer;
    color: #2271b1;
    padding: 2px 0;
}

.dgptm-choice-texts {
    margin: 6px 0 0 12px;
    padding-left: 12px;
}

.dgptm-choice-texts li {
    margin: 2px 0;
}

.dgptm-choice-numbers {
    margin: 6px 0 0 12px;
    font-family: monospace;
    color: #555;
}
```

- [ ] **Step 4: Manuelles Testen**

1. Testumfrage mit 3 Fragen, davon Frage 2 als datenschutzrelevant markiert.
2. Öffentlichen Ergebnis-Link aufrufen (`/umfrage-ergebnisse/<token>`).
3. Erwartet: Frage 1 und 3 erscheinen, Frage 2 fehlt komplett (keine Lücke, kein Platzhalter).
4. Admin-Ergebnisseite der gleichen Umfrage: alle 3 Fragen da, Frage 2 mit rotem Banner.
5. Choices mit Text/Zahl-Anhang auch auf Public korrekt gerendert (wie in Admin).

- [ ] **Step 5: Commit**

```bash
git add modules/business/umfragen/templates/public-results.php modules/business/umfragen/assets/css/frontend.css
git commit -m "feat(umfragen): Public-Results filtert Datenschutz-Fragen + nutzt Helper"
```

---

## Abschluss

- [ ] **Final Check: Alle Commits auf `feature/webinar-crm-sync`** (oder auf separatem Branch — siehe Deployment-Hinweis unten).
- [ ] **Deployment via Cherry-Pick auf `main`**, analog zum bisherigen Muster (nur umfragen-Commits, nicht die Webinar-CRM-Sync-Commits).

**Deployment-Hinweis:** Alle 8 Task-Commits gemeinsam cherry-picken (oder eine zweite, umfragen-only Branch wäre sauberer). Nach dem Push auf `main` läuft GitHub Actions → Deploy → auf dem ersten Admin-Seitenaufruf greift `ensure_tables()` und legt die neue Spalte via ALTER TABLE an.
