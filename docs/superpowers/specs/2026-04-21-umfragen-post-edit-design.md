# Umfragen: Nachträgliche Bearbeitung abgesendeter Antworten

**Datum:** 2026-04-21
**Modul:** `modules/business/umfragen`
**Status:** Design genehmigt, bereit für Implementierungsplan

## Problem

Nach dem Absenden einer Umfrage sieht der Nutzer ausschließlich die statische Meldung "Sie haben diese Umfrage bereits ausgefüllt" und kann seine Antworten nicht mehr korrigieren. Für Umfragen, in denen Nutzer über einen Zeitraum Daten sammeln oder Antworten iterativ verfeinern sollen (z.B. Gutachten, Selbst-Assessments, Pflege-Registry-Einträge), ist das zu restriktiv.

## Ziel

Ein neues Frage-Survey-Flag "Antworten nachträglich bearbeitbar" erlaubt es Nutzern, ihre eigene, bereits abgesendete Antwort erneut zu öffnen und zu verändern — so lange die Umfrage im Status `active` ist. Sobald der Administrator die Umfrage auf `closed` schaltet, ist die Bearbeitung gesperrt.

## Nicht-Ziele

- Kein Versions-Historie / Undo pro Antwort (komplette Ersetzung, keine Change-Log).
- Kein Edit durch Admin im Namen des Nutzers (getrennte Funktion, out of scope).
- Kein automatisches Re-Opening bei Umfrage-Status-Rückwechsel von `closed` auf `active`.

## Datenmodell

### Neue Spalte auf `dgptm_surveys`

```sql
allow_post_edit TINYINT(1) NOT NULL DEFAULT 0
```

### Neue Spalte auf `dgptm_survey_responses`

```sql
last_edited_at DATETIME DEFAULT NULL
```

**Bedeutung:**
- `completed_at` bleibt nach dem ersten Absenden unverändert — Ursprungszeitpunkt der Abgabe.
- `last_edited_at` wird bei jeder Edit-Aktion auf `NOW()` gesetzt. `NULL` bedeutet: nie bearbeitet.

### Migration

Beide Spalten via bestehendem `ensure_columns()`-Pattern im Installer. Version-Bump `DGPTM_UMFRAGEN_VERSION` von `1.7.0` auf `1.8.0` triggert die Migration beim nächsten Admin-Aufruf.

### Logische Voraussetzung

Das Flag ist nur sinnvoll, wenn der Nutzer beim Wiederkommen identifizierbar ist:
- `duplicate_check` ∈ (`cookie`, `ip`, `cookie_ip`) — Cookie/IP-basierte Identifikation
- ODER `access_mode = 'logged_in'` — via user_id

Bei `duplicate_check = 'none'` und nicht-logged-in-Modus ist "eigene Antwort wiederfinden" technisch nicht möglich. Das Editor-UI zeigt einen Warn-Badge bei dieser Konfiguration, blockiert aber nicht hart (der Admin kann die Einstellung sinnvoll kombinieren).

## Frontend-Flow

### Verzweigung im Shortcode

In `DGPTM_Survey_Frontend::render_shortcode()` wird vor der bestehenden "bereits ausgefüllt"-Meldung geprüft:

```
if (has_already_responded && survey.status === 'active' && survey.allow_post_edit) {
    return render_edit_prompt(survey, response_id);
}
// Bestehender Pfad bleibt unverändert:
if (has_already_responded) {
    return '<p>Sie haben diese Umfrage bereits ausgefüllt. Vielen Dank!</p>';
}
```

### Neue Methode `render_edit_prompt()`

Zeigt zweistufig:

```
+-----------------------------------------------------------+
| Sie haben diese Umfrage bereits ausgefüllt am {date}.     |
| Zuletzt geändert am {last_edited_at}.   [optional]        |
|                                                           |
| [Antworten bearbeiten]                                    |
+-----------------------------------------------------------+
```

Klick auf "Antworten bearbeiten" → lädt das bestehende `frontend-form.php`-Template mit `$resume_data`, das aus der completed Response statt aus einer in_progress Response gefüllt wird.

### Identifikation der eigenen Response

Die `response_id` wird über dasselbe Matching ermittelt, das `has_already_responded()` verwendet:
- Logged-in-User: `WHERE survey_id = %d AND user_id = %d AND status = 'completed'`
- Cookie-basiert: `WHERE survey_id = %d AND respondent_cookie = %s AND status = 'completed'`
- IP-basiert: `WHERE survey_id = %d AND respondent_ip = %s AND status = 'completed'`

### Edit-Modus-Signal im Formular

Wenn `response_id > 0` gesetzt ist UND die geladene Response `status = 'completed'` hat:
- Hinweis-Banner über dem Formular: **"Du bearbeitest deine bereits abgesendete Antwort. Änderungen überschreiben die bisherigen Werte."**
- Submit-Button-Label: **"Änderungen speichern"** (statt "Absenden")

Diese Unterscheidung erfolgt im Template (`frontend-form.php`) anhand einer neuen Variable, die vom Shortcode gesetzt wird (z.B. `$edit_mode = true`).

## Submit-Handler

### Bestehende Logik

`DGPTM_Survey_Frontend::ajax_submit_survey()` unterstützt bereits den Update-Pfad, wenn `response_id > 0`:

```php
if ($response_id) {
    $wpdb->update($responses_table, [
        'status'       => 'completed',
        'completed_at' => $now,
        ...
    ], ['id' => $response_id]);
    $wpdb->delete($answers_table, ['response_id' => $response_id]);
}
```

### Neue Unterscheidung: Edit-Submit vs. Resume-Submit

Vor dem Update wird der aktuelle Status der Response geprüft:

```php
$existing_status = $wpdb->get_var($wpdb->prepare(
    "SELECT status FROM {$responses_table} WHERE id = %d",
    $response_id
));

$update_data = [
    'respondent_ip'    => $ip,
    'respondent_name'  => ...,
    'respondent_email' => ...,
];

if ($existing_status === 'completed') {
    // Edit-Submit: completed_at unverändert lassen, last_edited_at setzen
    $update_data['last_edited_at'] = $now;
    $is_edit = true;
} else {
    // Resume-Submit (in_progress → completed): completed_at setzen
    $update_data['status']       = 'completed';
    $update_data['completed_at'] = $now;
    $is_edit = false;
}

$wpdb->update($responses_table, $update_data, ['id' => $response_id]);
$wpdb->delete($answers_table, ['response_id' => $response_id]);
// Neue Antworten einfügen (bestehender Code)
```

### Duplicate-Check überspringen im Edit-Pfad

Der bestehende Check `if ($this->has_already_responded($survey))` am Anfang von `ajax_submit_survey` würde jeden Edit-Submit blockieren. Daher: wenn `response_id > 0` UND die Response existiert UND dem aktuellen Nutzer gehört (Cookie/user_id-Match) UND `allow_post_edit = 1` → Check überspringen. Andernfalls greift der Check wie bisher.

### Status-Guard

Die bestehende Prüfung `"SELECT * FROM surveys WHERE id = %d AND status = 'active'"` wird unverändert gelassen. Wenn die Umfrage während einer offenen Edit-Session auf `closed` wechselt, erhält der Nutzer beim Submit den Fehler "Umfrage nicht verfügbar". Dokumentiert, akzeptabler Edge-Case.

### Audit-Log

Ergänzender Logeintrag im Edit-Pfad:

```php
dgptm_log_info('Umfrage-Antwort bearbeitet: Survey=' . $survey_id . ', Response=' . $response_id, 'umfragen');
```

## Editor-UI

### Checkbox in Admin-Editor

In `templates/admin-survey-edit.php` neben `allow_save_resume`:

```php
<label>
    <input type="checkbox" name="allow_post_edit" value="1" <?php checked($survey ? $survey->allow_post_edit : 0); ?>>
    Antworten nachträglich bearbeitbar (bis zur Schließung)
</label>
```

### Checkbox in Frontend-Editor

In `templates/frontend-editor.php` + `assets/js/frontend-editor.js` analog mit Klasse `dgptm-fe-allow-post-edit`.

### Save-Pfade

`ajax_save_survey()` nimmt den neuen Key `allow_post_edit` entgegen und persistiert als `absint()`. Analog zur bestehenden Behandlung von `allow_save_resume`.

### Konfigurationswarnung

Wenn `allow_post_edit = 1` UND `duplicate_check = 'none'` UND `access_mode != 'logged_in'`: oranger Hinweis-Badge im Editor — **"⚠ Editieren erfordert Duplikatschutz oder Login-Pflicht."** Keine harte Blockade, nur Hinweis per JS bei Änderung der betreffenden Felder.

## Admin-Ergebnis-Ansicht

In `templates/admin-survey-results.php` innerhalb der Einzelantworten-Tabelle:

- Wenn `last_edited_at IS NOT NULL` → Zusatz-Info in der Datumsspalte: `{completed_at} (bearbeitet: {last_edited_at})`
- Alternative: separate Spalte "Letzte Änderung" mit dem Timestamp oder einem em-Dash bei NULL

Die Entscheidung zur Darstellung bleibt der Implementierung überlassen; die Daten sind verfügbar.

## Dateien, die geändert werden

| Datei | Änderung |
|---|---|
| `includes/class-survey-installer.php` | Schema + `ensure_columns()` für `allow_post_edit` + `last_edited_at` |
| `dgptm-umfragen.php` | `DGPTM_UMFRAGEN_VERSION` → `1.8.0`, Plugin-Header Version |
| `module.json` | `"version": "1.8.0"` |
| `includes/class-survey-admin.php` | `ajax_save_survey` akzeptiert `allow_post_edit`; Ergebnis-Anzeige mit `last_edited_at` |
| `includes/class-survey-frontend.php` | `render_shortcode` Verzweigung + neue `render_edit_prompt()`; `ajax_submit_survey` Edit-vs-Resume-Unterscheidung; Duplicate-Check-Bypass im Edit-Pfad |
| `templates/frontend-form.php` | Edit-Modus-Banner + Submit-Button-Label-Switch |
| `templates/admin-survey-edit.php` | Checkbox "Antworten nachträglich bearbeitbar" |
| `templates/frontend-editor.php` | Checkbox analog mit `fe`-Prefix |
| `templates/admin-survey-results.php` | `last_edited_at` in Einzelantworten-Tabelle darstellen |
| `assets/js/frontend-editor.js` | Flag in save-Objekt mitsenden |
| `assets/js/admin.js` | Flag in save-Objekt mitsenden; Hinweis-Badge bei unpassender Konfig |
| `assets/css/frontend.css` | Style für Edit-Banner + "Antworten bearbeiten"-Button |

## Kompatibilität

- Bestehende Umfragen ohne Flag (`allow_post_edit = 0`, Default) verhalten sich exakt wie bisher. Keine Regression.
- Bereits existierende completed Responses haben `last_edited_at = NULL` und erscheinen ohne "bearbeitet"-Zusatz — natürlicher Fallback.
- DB-Migration additiv, keine Datenverluste.

## Testing

Manueller Testablauf (kein Test-Framework im Projekt):

1. Umfrage mit `allow_post_edit = 1`, `duplicate_check = 'cookie'`, Status `active` anlegen.
2. Als Nutzer ausfüllen, absenden → "Vielen Dank"-Bestätigung.
3. Seite neu laden → zweistufige Anzeige mit "Antworten bearbeiten"-Button erscheint.
4. Button klicken → Formular mit vorausgefüllten Werten + Edit-Banner.
5. Eine Antwort ändern, speichern → erfolgreiche Rückmeldung.
6. DB prüfen: `completed_at` unverändert, `last_edited_at` auf aktuellen Zeitpunkt gesetzt, Antworten überschrieben.
7. Admin-Ergebnis-Ansicht: Zusatz "bearbeitet: {ts}" erscheint in der Tabelle.
8. Umfrage auf `closed` schalten → erneuter Seitenbesuch zeigt wieder die Standard-"bereits ausgefüllt"-Meldung ohne Edit-Button.
9. Regressionstest: Umfrage ohne Flag → verhält sich wie vorher, keine Edit-Option.
