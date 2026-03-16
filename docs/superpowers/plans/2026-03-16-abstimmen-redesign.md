# Abstimmen-Addon Redesign — Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Redesign the voting system for anonymous voting, timer countdown, modern beamer display, and configurable majority rules.

**Architecture:** Incremental changes to existing abstimmen-addon module. DB migration via `dgptm_maybe_upgrade_db()`. Backend-first approach: schema → helpers → AJAX → views.

**Tech Stack:** PHP 7.4+ (WordPress), Chart.js 4.x, Vanilla JS, CSS custom properties

**Spec:** `docs/superpowers/specs/2026-03-16-abstimmen-redesign-design.md`

**Base path:** `modules/business/abstimmen-addon/`

**No test framework available.** Testing is manual via WordPress admin dashboard, System Logs, and browser console. Each task includes manual verification steps.

---

## Chunk 1: Database & Helpers

### Task 1: Database Schema Migration

**Files:**
- Modify: `includes/common/install.php`

- [ ] **Step 1:** Read current `includes/common/install.php` to understand existing `dgptm_maybe_upgrade_db()` pattern.

- [ ] **Step 2:** Add new columns in `dgptm_maybe_upgrade_db()`. Insert after the existing column-add logic (after the `$has` lambda). Add these ALTER TABLE statements:

```php
// === v4.1.0 Redesign: Timer, Majority, Anonymous ===
$q = $wpdb->prefix . 'dgptm_abstimmung_poll_questions';
$p = $wpdb->prefix . 'dgptm_abstimmung_polls';
$pt = $wpdb->prefix . 'dgptm_abstimmung_participants';

if (!$has($q, 'auto_close')) {
    $wpdb->query("ALTER TABLE $q ADD COLUMN auto_close TINYINT(1) NOT NULL DEFAULT 0");
}
if (!$has($q, 'majority_type')) {
    $wpdb->query("ALTER TABLE $q ADD COLUMN majority_type VARCHAR(20) NOT NULL DEFAULT 'simple'");
}
if (!$has($q, 'quorum')) {
    $wpdb->query("ALTER TABLE $q ADD COLUMN quorum INT NOT NULL DEFAULT 0");
}
if (!$has($q, 'started_at')) {
    $wpdb->query("ALTER TABLE $q ADD COLUMN started_at DATETIME DEFAULT NULL");
}
if (!$has($p, 'guest_voting')) {
    $wpdb->query("ALTER TABLE $p ADD COLUMN guest_voting TINYINT(1) NOT NULL DEFAULT 1");
}
if (!$has($pt, 'voted_questions')) {
    $wpdb->query("ALTER TABLE $pt ADD COLUMN voted_questions TEXT DEFAULT NULL");
}
```

- [ ] **Step 3:** Update the version check at the top of `dgptm_maybe_upgrade_db` — change the version comparison to also trigger on `4.1.0`:

Replace: `if (version_compare($current, DGPTMVOTE_VERSION, '>=')) return;`
This already works since DGPTMVOTE_VERSION is `4.0.0`. But update the `add_option`/`update_option` at the end to set `4.1.0`.

- [ ] **Step 4:** Also update the `CREATE TABLE` statements in `dgptm_activate_plugin()` to include the new columns for fresh installs.

- [ ] **Step 5:** Update `DGPTM_ABSTIMMEN_VERSION` and `DGPTMVOTE_VERSION` constants in `abstimmen-addon.php` from `4.0.0` to `4.1.0`.

- [ ] **Step 6:** Run `php -l includes/common/install.php` to verify syntax.

- [ ] **Step 7:** Commit.

```bash
git add includes/common/install.php abstimmen-addon.php
git commit -m "feat(abstimmen): DB schema for timer, majority, quorum, anonymous tracking"
```

---

### Task 2: Helper Functions

**Files:**
- Modify: `includes/common/helpers.php`

- [ ] **Step 1:** Read current `includes/common/helpers.php`.

- [ ] **Step 2:** Add majority evaluation helper after `dgptm_get_beamer_state()`:

```php
/**
 * Evaluate majority result for a question.
 *
 * @param array  $vote_counts  Associative array: choice_index => count
 * @param int    $total_votes  Total valid votes cast
 * @param int    $attendees    Number of attendees (participants)
 * @param string $majority_type 'simple', 'two_thirds', 'absolute'
 * @param int    $quorum       Minimum votes required (0 = none)
 * @return array ['passed' => bool, 'winner_index' => int|null, 'label' => string, 'quorum_met' => bool]
 */
if (!function_exists('dgptm_evaluate_majority')) {
    function dgptm_evaluate_majority($vote_counts, $total_votes, $attendees, $majority_type = 'simple', $quorum = 0) {
        $result = [
            'passed'       => false,
            'winner_index' => null,
            'label'        => '',
            'quorum_met'   => ($quorum <= 0 || $total_votes >= $quorum),
            'quorum'       => $quorum,
            'total_votes'  => $total_votes,
            'attendees'    => $attendees,
        ];

        if (!$result['quorum_met']) {
            $result['label'] = 'Quorum nicht erreicht (' . $total_votes . '/' . $quorum . ')';
            return $result;
        }

        if (empty($vote_counts) || $total_votes === 0) {
            $result['label'] = 'Keine Stimmen abgegeben';
            return $result;
        }

        // Find the winner (highest votes)
        arsort($vote_counts);
        $winner_index = array_key_first($vote_counts);
        $winner_votes = $vote_counts[$winner_index];
        $result['winner_index'] = $winner_index;

        $threshold = 0;
        $rule_label = '';

        switch ($majority_type) {
            case 'two_thirds':
                $threshold = $total_votes * (2 / 3);
                $rule_label = '2/3-Mehrheit';
                break;
            case 'absolute':
                $threshold = $attendees / 2;
                $rule_label = 'Absolute Mehrheit';
                break;
            case 'simple':
            default:
                $threshold = $total_votes / 2;
                $rule_label = 'Einfache Mehrheit';
                break;
        }

        $result['passed'] = ($winner_votes > $threshold);
        $status = $result['passed'] ? 'Angenommen' : 'Abgelehnt';
        $result['label'] = $status . ' (' . $rule_label . ')';

        return $result;
    }
}
```

- [ ] **Step 3:** Add timer helper:

```php
/**
 * Calculate remaining seconds for a question's timer.
 *
 * @param object $question DB row with started_at and time_limit
 * @return int|null Remaining seconds, null if no timer, negative if expired
 */
if (!function_exists('dgptm_get_remaining_seconds')) {
    function dgptm_get_remaining_seconds($question) {
        if (empty($question->time_limit) || (int)$question->time_limit <= 0) {
            return null;
        }
        if (empty($question->started_at)) {
            return (int)$question->time_limit;
        }
        $started = strtotime($question->started_at);
        $expires = $started + (int)$question->time_limit;
        return $expires - time();
    }
}

/**
 * Check if a question's timer has expired and auto-close if configured.
 *
 * @param object $question DB row
 * @return bool True if question was auto-closed
 */
if (!function_exists('dgptm_check_auto_close')) {
    function dgptm_check_auto_close($question) {
        if (empty($question->auto_close) || $question->status !== 'active') {
            return false;
        }
        $remaining = dgptm_get_remaining_seconds($question);
        if ($remaining !== null && $remaining <= 0) {
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'dgptm_abstimmung_poll_questions',
                ['status' => 'stopped', 'ended' => current_time('mysql')],
                ['id' => $question->id]
            );
            return true;
        }
        return false;
    }
}
```

- [ ] **Step 4:** Add anonymous vote tracking helper:

```php
/**
 * Check if a participant already voted on a specific question (anonymous tracking).
 *
 * @param int $participant_id Participant DB row ID
 * @param int $question_id   Question ID
 * @return bool
 */
if (!function_exists('dgptm_has_voted_anonymous')) {
    function dgptm_has_voted_anonymous($participant_id, $question_id) {
        global $wpdb;
        $raw = $wpdb->get_var($wpdb->prepare(
            "SELECT voted_questions FROM {$wpdb->prefix}dgptm_abstimmung_participants WHERE id = %d",
            $participant_id
        ));
        if (empty($raw)) return false;
        $arr = json_decode($raw, true);
        return is_array($arr) && in_array((int)$question_id, $arr, true);
    }
}

/**
 * Mark that a participant voted on a question (anonymous tracking).
 *
 * @param int $participant_id Participant DB row ID
 * @param int $question_id   Question ID
 */
if (!function_exists('dgptm_mark_voted_anonymous')) {
    function dgptm_mark_voted_anonymous($participant_id, $question_id) {
        global $wpdb;
        $raw = $wpdb->get_var($wpdb->prepare(
            "SELECT voted_questions FROM {$wpdb->prefix}dgptm_abstimmung_participants WHERE id = %d",
            $participant_id
        ));
        $arr = !empty($raw) ? json_decode($raw, true) : [];
        if (!is_array($arr)) $arr = [];
        if (!in_array((int)$question_id, $arr, true)) {
            $arr[] = (int)$question_id;
            $wpdb->update(
                $wpdb->prefix . 'dgptm_abstimmung_participants',
                ['voted_questions' => wp_json_encode($arr)],
                ['id' => $participant_id]
            );
        }
    }
}

/**
 * Find participant record for current user/cookie.
 *
 * @param int $poll_id
 * @return object|null Participant DB row
 */
if (!function_exists('dgptm_get_current_participant')) {
    function dgptm_get_current_participant($poll_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'dgptm_abstimmung_participants';

        if (is_user_logged_in()) {
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE poll_id = %d AND user_id = %d LIMIT 1",
                $poll_id, get_current_user_id()
            ));
        }

        $cookie = isset($_COOKIE[DGPTMVOTE_COOKIE]) ? sanitize_text_field($_COOKIE[DGPTMVOTE_COOKIE]) : '';
        if (!empty($cookie)) {
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE poll_id = %d AND cookie_id = %s LIMIT 1",
                $poll_id, $cookie
            ));
        }

        return null;
    }
}
```

- [ ] **Step 5:** Run `php -l includes/common/helpers.php`.

- [ ] **Step 6:** Commit.

```bash
git add includes/common/helpers.php
git commit -m "feat(abstimmen): helper functions for majority, timer, anonymous tracking"
```

---

## Chunk 2: Vote Logic & Admin AJAX

### Task 3: Anonymous Vote Logic

**Files:**
- Modify: `includes/ajax/vote.php`

- [ ] **Step 1:** Read current `includes/ajax/vote.php` completely, especially `dgptm_cast_vote_fn()`.

- [ ] **Step 2:** Rewrite `dgptm_cast_vote_fn()` to handle anonymous votes differently. The key changes:

Inside `dgptm_cast_vote_fn()`, after validating the question is active and choices are within `max_votes`:

```php
// Check auto-close before accepting vote
if (dgptm_check_auto_close($q)) {
    wp_send_json_error('Die Abstimmungszeit ist abgelaufen.');
}

// Get question's anonymous flag
$is_anon = !empty($q->is_anonymous);

if ($is_anon) {
    // === ANONYMOUS VOTING ===
    // Find participant
    $participant = dgptm_get_current_participant((int)$q->poll_id);
    if (!$participant) {
        wp_send_json_error('Bitte treten Sie zuerst der Abstimmung bei.');
    }

    // Check if already voted (no re-voting for anonymous)
    if (dgptm_has_voted_anonymous($participant->id, $qid)) {
        wp_send_json_error('Sie haben bei dieser Frage bereits abgestimmt. Anonyme Stimmen koennen nicht geaendert werden.');
    }

    // Insert votes WITHOUT identifying data
    $vt = current_time('mysql');
    foreach ($choices as $ch) {
        $wpdb->insert($wpdb->prefix . 'dgptm_abstimmung_votes', [
            'question_id'  => $qid,
            'choice_index' => (int)$ch,
            'user_id'      => 0,
            'vote_time'    => $vt,
            'ip'           => 'anonymous',
            'is_invalid'   => 0,
        ]);
    }

    // Track that this participant voted (but not how)
    dgptm_mark_voted_anonymous($participant->id, $qid);

    do_action('dgptm_vote_cast', $qid, $choices, 0, (int)$q->poll_id);
    wp_send_json_success('Ihre Stimme wurde anonym gezaehlt.');

} else {
    // === NON-ANONYMOUS VOTING (existing logic) ===
    // ... keep existing code for identified votes (delete old, insert new) ...
}
```

- [ ] **Step 3:** Keep the existing non-anonymous logic intact. Just wrap it in the `else` branch.

- [ ] **Step 4:** Run `php -l includes/ajax/vote.php`.

- [ ] **Step 5:** Commit.

```bash
git add includes/ajax/vote.php
git commit -m "feat(abstimmen): anonymous vote logic with participant tracking"
```

---

### Task 4: Admin AJAX — New Fields CRUD + Auto-Close

**Files:**
- Modify: `includes/admin/admin-ajax.php`

- [ ] **Step 1:** Read current `includes/admin/admin-ajax.php` completely.

- [ ] **Step 2:** Update `dgptm_add_poll_question_fn()` to accept new fields:

Add after existing field parsing:
```php
$time_limit    = isset($_POST['time_limit']) ? absint($_POST['time_limit']) : 0;
$auto_close    = !empty($_POST['auto_close']) ? 1 : 0;
$majority_type = isset($_POST['majority_type']) ? sanitize_text_field($_POST['majority_type']) : 'simple';
$quorum        = isset($_POST['quorum']) ? absint($_POST['quorum']) : 0;

// Validate majority_type
if (!in_array($majority_type, ['simple', 'two_thirds', 'absolute'], true)) {
    $majority_type = 'simple';
}
```

Add these fields to the `$wpdb->insert()` call's data array.

- [ ] **Step 3:** Update `dgptm_update_poll_question_fn()` similarly — add the new fields to the UPDATE query.

- [ ] **Step 4:** Update `dgptm_activate_poll_question_fn()` — set `started_at = current_time('mysql')` when activating:

```php
$wpdb->update(
    $wpdb->prefix . 'dgptm_abstimmung_poll_questions',
    [
        'status'     => 'active',
        'created'    => current_time('mysql'),
        'started_at' => current_time('mysql'),
    ],
    ['id' => $qid]
);
```

- [ ] **Step 5:** Update `dgptm_create_poll_fn()` to accept `guest_voting`:

```php
$guest_voting = isset($_POST['guest_voting']) ? 1 : 0;
```

Add to the insert data array.

- [ ] **Step 6:** Add auto-close check to `dgptm_get_poll_details_fn()` — before building response HTML, check if active question timer expired:

```php
// Auto-close check for active questions
$active_q = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_poll_questions WHERE poll_id = %d AND status = 'active' LIMIT 1",
    $pid
));
if ($active_q) {
    dgptm_check_auto_close($active_q);
}
```

- [ ] **Step 7:** Run `php -l includes/admin/admin-ajax.php`.

- [ ] **Step 8:** Commit.

```bash
git add includes/admin/admin-ajax.php
git commit -m "feat(abstimmen): CRUD for timer, majority, quorum, guest_voting fields"
```

---

## Chunk 3: Beamer Redesign

### Task 5: Beamer Payload — Timer & Majority

**Files:**
- Modify: `includes/beamer/payload.php`

- [ ] **Step 1:** Read current `includes/beamer/payload.php` completely.

- [ ] **Step 2:** Rewrite `dgptm_get_beamer_payload_fn()`. Key changes:

Add to the payload array:
```php
// Timer data
$remaining = null;
$auto_closed = false;
if ($active_question) {
    $auto_closed = dgptm_check_auto_close($active_question);
    if ($auto_closed) {
        // Re-fetch question after auto-close
        $active_question = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_poll_questions WHERE id = %d",
            $active_question->id
        ));
    }
    $remaining = dgptm_get_remaining_seconds($active_question);
}

$payload['timer'] = [
    'remaining_seconds' => $remaining,
    'time_limit'        => $active_question ? (int)$active_question->time_limit : 0,
    'auto_close'        => $active_question ? (int)$active_question->auto_close : 0,
    'auto_closed'       => $auto_closed,
];
```

Add majority evaluation to stopped+released questions:
```php
if ($question->status === 'stopped' && $question->results_released) {
    // Count votes per choice
    $votes = $wpdb->get_results($wpdb->prepare(
        "SELECT choice_index, COUNT(*) as cnt FROM {$wpdb->prefix}dgptm_abstimmung_votes WHERE question_id = %d AND is_invalid = 0 GROUP BY choice_index",
        $question->id
    ));
    $vote_counts = [];
    $total = 0;
    foreach ($votes as $v) {
        $vote_counts[(int)$v->choice_index] = (int)$v->cnt;
        $total += (int)$v->cnt;
    }

    $attendees = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}dgptm_abstimmung_participants WHERE poll_id = %d",
        $poll->id
    ));

    $majority = dgptm_evaluate_majority(
        $vote_counts, $total, $attendees,
        $question->majority_type ?? 'simple',
        (int)($question->quorum ?? 0)
    );

    // Add to question payload
    $q_data['majority'] = $majority;
    $q_data['vote_counts'] = $vote_counts;
    $q_data['total_votes'] = $total;
}
```

- [ ] **Step 3:** Ensure the payload includes `is_anonymous`, `majority_type`, `quorum` for the active question.

- [ ] **Step 4:** Run `php -l includes/beamer/payload.php`.

- [ ] **Step 5:** Commit.

```bash
git add includes/beamer/payload.php
git commit -m "feat(abstimmen): beamer payload with timer, auto-close, majority evaluation"
```

---

### Task 6: Beamer View — Corporate Dark Redesign

**Files:**
- Rewrite: `includes/beamer/view.php`

This is a complete rewrite. The file currently contains inline HTML/CSS/JS for the beamer.

- [ ] **Step 1:** Read current `includes/beamer/view.php` to understand the existing AJAX polling structure.

- [ ] **Step 2:** Rewrite `dgptm_beamer_view()` with the Corporate Dark design. The complete function should:

**HTML structure:**
```html
<div id="dgptm_beamer" style="...corporate dark base styles...">
  <!-- Accent bar -->
  <div class="dgptm-beamer-accent"></div>
  <!-- Clock / Timer -->
  <div id="dgptm_beamerClock" class="dgptm-beamer-clock">--:--</div>
  <!-- Poll name -->
  <div id="dgptm_beamerPollName" class="dgptm-beamer-poll-name"></div>
  <!-- Main content area (switches between states) -->
  <div id="dgptm_beamerContent" class="dgptm-beamer-content"></div>
  <!-- QR code -->
  <div id="dgptm_beamerQR" class="dgptm-beamer-qr"></div>
</div>
```

**CSS (inline in `<style>` tag):** Corporate Dark theme with:
- `#111827` background, `4px` accent gradient bar at top
- Clock: top-left, `16px`, white. Red + pulsing when countdown < 10s
- `.dgptm-beamer-result-card`: colored background at 12% opacity, border, large percent, option text, vote count
- Result colors: `--color-yes: #4ade80`, `--color-no: #f87171`, `--color-abstain: #fbbf24`, `--color-opt4: #60a5fa`, `--color-opt5: #a78bfa`
- Progress bar styling with gradient fill
- Animations: `@keyframes pulse` for timer, fade-in for results

**JavaScript:** Single polling loop that fetches `dgptm_get_beamer_payload` and renders based on state:

```javascript
function renderBeamer(data) {
    // Update clock
    updateClock(data);

    // Determine display state
    if (data.beamer_state.mode === 'results_all') {
        renderAllResults(data);
    } else if (data.beamer_state.mode === 'results_one') {
        renderSingleResult(data);
    } else if (data.active_question && data.active_question.status === 'active') {
        renderActiveVoting(data);
    } else if (data.active_question && data.active_question.status === 'stopped' && !data.active_question.results_released) {
        renderWaitingForRelease(data);
    } else {
        renderIdle(data);
    }
}

function updateClock(data) {
    var el = document.getElementById('dgptm_beamerClock');
    if (data.timer && data.timer.remaining_seconds !== null && data.timer.remaining_seconds > 0) {
        // Countdown mode
        var mins = Math.floor(data.timer.remaining_seconds / 60);
        var secs = data.timer.remaining_seconds % 60;
        el.textContent = mins + ':' + String(secs).padStart(2, '0');
        el.classList.toggle('dgptm-timer-urgent', data.timer.remaining_seconds < 10);
        el.classList.add('dgptm-timer-active');
    } else {
        // Clock mode
        var now = new Date();
        el.textContent = String(now.getHours()).padStart(2,'0') + ':' + String(now.getMinutes()).padStart(2,'0');
        el.classList.remove('dgptm-timer-active', 'dgptm-timer-urgent');
    }
}

function renderResultCards(question) {
    var choices = JSON.parse(question.choices);
    var colors = ['#4ade80','#f87171','#fbbf24','#60a5fa','#a78bfa','#fb923c','#e879f9','#34d399'];
    var html = '<div class="dgptm-beamer-question-title">' + escHtml(question.question) + '</div>';
    html += '<div class="dgptm-beamer-cards">';
    for (var i = 0; i < choices.length; i++) {
        var count = (question.vote_counts && question.vote_counts[i]) || 0;
        var pct = question.total_votes > 0 ? Math.round(count / question.total_votes * 100) : 0;
        var color = colors[i % colors.length];
        html += '<div class="dgptm-beamer-result-card" style="border-color:' + color + ';background:' + hexToRgba(color, 0.12) + '">';
        html += '<div class="dgptm-card-pct" style="color:' + color + '">' + pct + '%</div>';
        html += '<div class="dgptm-card-label">' + escHtml(choices[i]) + '</div>';
        html += '<div class="dgptm-card-count">' + count + ' Stimmen</div>';
        html += '</div>';
    }
    html += '</div>';
    // Majority result
    if (question.majority) {
        var icon = question.majority.passed ? '✓' : '✗';
        var cls = question.majority.passed ? 'dgptm-result-passed' : 'dgptm-result-failed';
        html += '<div class="dgptm-beamer-result-text ' + cls + '">' + icon + ' ' + escHtml(question.majority.label);
        html += ' · ' + question.total_votes + ' Stimmen';
        if (question.majority.quorum > 0) {
            html += ' · Quorum ' + (question.majority.quorum_met ? 'erreicht' : 'nicht erreicht');
        }
        html += '</div>';
    }
    return html;
}
```

- [ ] **Step 3:** Include Chart.js CDN load for the beamer view.

- [ ] **Step 4:** Run `php -l includes/beamer/view.php`.

- [ ] **Step 5:** Commit.

```bash
git add includes/beamer/view.php
git commit -m "feat(abstimmen): corporate dark beamer with result cards, timer, majority display"
```

---

## Chunk 4: Manager UI & Member Vote

### Task 7: Manager UI — New Fields

**Files:**
- Modify: `includes/admin/manage-poll.php`

- [ ] **Step 1:** Read current `includes/admin/manage-poll.php` completely.

- [ ] **Step 2:** In the "Create poll" form, add guest_voting checkbox:

```html
<label>Gaeste erlauben (QR + Name)?
  <input type="checkbox" name="guest_voting" checked>
</label><br><br>
```

- [ ] **Step 3:** In the "Add question" form (rendered in AJAX details), add new fields. These are in `dgptm_get_poll_details_fn()` in `admin-ajax.php`. Add after the existing `is_anonymous` checkbox:

```html
<label>Zeitlimit (Sekunden, 0 = kein Timer):
  <input type="number" name="time_limit" value="0" min="0" style="width:80px">
</label><br>
<label>
  <input type="checkbox" name="auto_close"> Automatisch schliessen nach Ablauf
</label><br>
<label>Mehrheitsregel:
  <select name="majority_type">
    <option value="simple">Einfache Mehrheit (&gt;50%)</option>
    <option value="two_thirds">2/3-Mehrheit</option>
    <option value="absolute">Absolute Mehrheit</option>
  </select>
</label><br>
<label>Quorum (0 = keins):
  <input type="number" name="quorum" value="0" min="0" style="width:80px">
</label><br>
```

- [ ] **Step 4:** In the question detail display (the AJAX-loaded HTML), show timer/majority config and the new "Ergebnis freigeben" button that also sets beamer state. Update the `dgptm_get_poll_details_fn()` in `admin-ajax.php` to show these fields for existing questions and include the timer/majority values in the edit form.

- [ ] **Step 5:** Run `php -l includes/admin/manage-poll.php` and `php -l includes/admin/admin-ajax.php`.

- [ ] **Step 6:** Commit.

```bash
git add includes/admin/manage-poll.php includes/admin/admin-ajax.php
git commit -m "feat(abstimmen): manager UI for timer, majority, quorum, guest voting"
```

---

### Task 8: Member Vote — Timer, Anonymous, Guest Gate

**Files:**
- Modify: `includes/public/member-vote.php`
- Modify: `includes/ajax/vote.php` (dgptm_get_member_view_fn)

- [ ] **Step 1:** Read current `includes/public/member-vote.php` and the `dgptm_get_member_view_fn()` in `vote.php`.

- [ ] **Step 2:** In `dgptm_get_member_view_fn()`, add guest gate check:

```php
// Check guest_voting setting
$poll = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}dgptm_abstimmung_polls WHERE status='active' LIMIT 1");
if ($poll && empty($poll->guest_voting) && !is_user_logged_in()) {
    wp_send_json_success([
        'html' => '<div class="dgptm-guest-blocked"><p>Bitte melden Sie sich an um abzustimmen.</p><a href="' . esc_url(wp_login_url(get_permalink())) . '" class="btn">Anmelden</a></div>',
        'has_active' => false,
    ]);
}
```

- [ ] **Step 3:** Add timer data to the member view response. In the active question HTML:

```php
// Timer info
$remaining = dgptm_get_remaining_seconds($question);
$timer_html = '';
if ($remaining !== null) {
    $timer_html = '<div class="dgptm-vote-timer" data-remaining="' . (int)$remaining . '" data-auto-close="' . (int)$question->auto_close . '">';
    $mins = floor(max(0, $remaining) / 60);
    $secs = max(0, $remaining) % 60;
    $timer_html .= '<span class="dgptm-countdown">' . $mins . ':' . str_pad($secs, 2, '0', STR_PAD_LEFT) . '</span>';
    $timer_html .= '</div>';
}
```

- [ ] **Step 4:** Add anonymous indicator and modified submit button:

```php
$anon_html = '';
$submit_label = 'Abstimmen';
if (!empty($question->is_anonymous)) {
    $anon_html = '<div class="dgptm-anon-notice">Diese Abstimmung ist anonym. Ihre Stimme kann nicht zu Ihnen zurueckverfolgt werden.</div>';
    $submit_label = 'Abstimmen (endgueltig)';
}

// Majority info
$majority_html = '';
$maj = $question->majority_type ?? 'simple';
$q_quorum = (int)($question->quorum ?? 0);
if ($maj !== 'simple' || $q_quorum > 0) {
    $labels = ['simple' => 'Einfache Mehrheit', 'two_thirds' => '2/3-Mehrheit', 'absolute' => 'Absolute Mehrheit'];
    $majority_html = '<div class="dgptm-majority-info">Erforderlich: ' . ($labels[$maj] ?? $maj);
    if ($q_quorum > 0) $majority_html .= ' · Quorum: ' . $q_quorum . ' Stimmen';
    $majority_html .= '</div>';
}
```

Include `$timer_html`, `$anon_html`, `$majority_html` in the rendered HTML.

- [ ] **Step 5:** Run `php -l includes/public/member-vote.php` and `php -l includes/ajax/vote.php`.

- [ ] **Step 6:** Commit.

```bash
git add includes/public/member-vote.php includes/ajax/vote.php
git commit -m "feat(abstimmen): member vote with timer, anonymous notice, guest gate"
```

---

## Chunk 5: Frontend JS/CSS

### Task 9: Frontend JavaScript — Countdown & Auto-Disable

**Files:**
- Modify: `assets/js/frontend.js`

- [ ] **Step 1:** Read current `assets/js/frontend.js`.

- [ ] **Step 2:** Add countdown timer logic to the VotingInterface. After the AJAX response renders the form, check for `.dgptm-vote-timer` elements:

```javascript
// Countdown timer for active voting
function initCountdown() {
    var timerEl = document.querySelector('.dgptm-vote-timer');
    if (!timerEl) return;
    var remaining = parseInt(timerEl.dataset.remaining, 10);
    var autoClose = parseInt(timerEl.dataset.autoClose, 10);
    var countdownEl = timerEl.querySelector('.dgptm-countdown');

    var interval = setInterval(function() {
        remaining--;
        if (remaining <= 0) {
            clearInterval(interval);
            countdownEl.textContent = '0:00';
            if (autoClose) {
                // Disable form
                var form = document.querySelector('#dgptm_memberVoteArea');
                if (form) {
                    form.classList.add('dgptm-vote-expired');
                    var btns = form.querySelectorAll('button, input[type=submit]');
                    btns.forEach(function(b) { b.disabled = true; });
                }
                timerEl.insertAdjacentHTML('afterend', '<div class="dgptm-expired-notice">Zeit abgelaufen</div>');
            } else {
                timerEl.insertAdjacentHTML('afterend', '<div class="dgptm-expired-notice dgptm-expired-open">Zeit abgelaufen — Abstimmung noch offen</div>');
            }
            return;
        }
        var m = Math.floor(remaining / 60);
        var s = remaining % 60;
        countdownEl.textContent = m + ':' + String(s).padStart(2, '0');
        timerEl.classList.toggle('dgptm-timer-urgent', remaining < 10);
    }, 1000);
}
```

Call `initCountdown()` after each successful member view AJAX load.

- [ ] **Step 3:** Commit.

```bash
git add assets/js/frontend.js
git commit -m "feat(abstimmen): frontend countdown timer with auto-disable"
```

---

### Task 10: Frontend CSS — Timer & Anonymous Styling

**Files:**
- Modify: `assets/css/frontend.css`

- [ ] **Step 1:** Read current `assets/css/frontend.css`.

- [ ] **Step 2:** Add styles:

```css
/* Timer */
.dgptm-vote-timer {
    text-align: center;
    margin: 10px 0;
    font-size: clamp(20px, 4vw, 32px);
    font-weight: 800;
    color: #333;
}
.dgptm-vote-timer.dgptm-timer-urgent .dgptm-countdown {
    color: #dc2626;
    animation: dgptm-pulse 0.8s ease-in-out infinite alternate;
}
@keyframes dgptm-pulse { from { opacity: 1; } to { opacity: 0.5; } }

/* Anonymous notice */
.dgptm-anon-notice {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 8px;
    padding: 10px 14px;
    margin: 10px 0;
    font-size: 13px;
    color: #1e40af;
}

/* Majority info */
.dgptm-majority-info {
    text-align: center;
    font-size: 12px;
    color: #6b7280;
    margin: 8px 0;
}

/* Expired state */
.dgptm-vote-expired { opacity: 0.5; pointer-events: none; }
.dgptm-expired-notice {
    text-align: center;
    padding: 12px;
    margin: 10px 0;
    border-radius: 8px;
    font-weight: 700;
    background: #fef2f2;
    color: #991b1b;
    border: 1px solid #fecaca;
}
.dgptm-expired-notice.dgptm-expired-open {
    background: #fffbeb;
    color: #92400e;
    border-color: #fde68a;
}

/* Guest blocked */
.dgptm-guest-blocked {
    text-align: center;
    padding: 30px;
}
.dgptm-guest-blocked .btn {
    display: inline-block;
    margin-top: 12px;
    padding: 10px 20px;
    background: #2d6cdf;
    color: #fff;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
}
```

- [ ] **Step 3:** Commit.

```bash
git add assets/css/frontend.css
git commit -m "feat(abstimmen): CSS for timer, anonymous notice, expired state"
```

---

## Chunk 6: Integration & Verification

### Task 11: Final Integration & Syntax Check

**Files:**
- All modified files

- [ ] **Step 1:** Run PHP syntax check on ALL modified files:

```bash
php -l includes/common/install.php
php -l includes/common/helpers.php
php -l includes/ajax/vote.php
php -l includes/admin/admin-ajax.php
php -l includes/admin/manage-poll.php
php -l includes/public/member-vote.php
php -l includes/beamer/view.php
php -l includes/beamer/payload.php
php -l abstimmen-addon.php
```

- [ ] **Step 2:** Verify no remaining `json_decode(get_option('dgptm_beamer_state'...` calls exist:

```bash
grep -r "json_decode.*dgptm_beamer_state" includes/ --include="*.php"
```

Expected: 0 results.

- [ ] **Step 3:** Verify all new DB columns are in both `dgptm_activate_plugin()` (CREATE TABLE) and `dgptm_maybe_upgrade_db()` (ALTER TABLE).

- [ ] **Step 4:** Final commit and push:

```bash
git add -A modules/business/abstimmen-addon/
git commit -m "feat(abstimmen): complete redesign - anonymous voting, timer, beamer, majority rules

- Anonymous voting: votes stored without user_id, participant tracking via voted_questions
- Timer: per-question countdown with optional auto-close
- Beamer: Corporate Dark design with result cards, countdown, majority display
- Majority rules: simple, 2/3, absolute, configurable quorum
- Guest gate: configurable per poll (WP-users only or guests via QR)
- DB migration: new columns for timer, majority, quorum, guest_voting, voted_questions"

git push origin main
```

---

## Manual Verification Checklist

After deployment, test in this order:

1. **DB Migration:** Visit WP Admin → columns should be added automatically via `admin_init`
2. **Create Poll:** New "Gaeste erlauben" checkbox visible
3. **Create Question:** Timer, Auto-Close, Mehrheitsregel, Quorum fields visible
4. **Activate Question:** Timer countdown starts on beamer
5. **Vote (logged-in):** Anonymous notice shows, vote succeeds, no user_id in DB
6. **Vote (guest):** Guest gate appears when `guest_voting=0`
7. **Timer Expiry:** Auto-close stops question, manual shows "noch offen"
8. **Stop + Release:** Manager stops question, releases results, beamer shows result cards
9. **Majority Display:** Correct "Angenommen/Abgelehnt" based on configured rule
10. **Beamer States:** Idle → Voting → Waiting → Results → All Results
