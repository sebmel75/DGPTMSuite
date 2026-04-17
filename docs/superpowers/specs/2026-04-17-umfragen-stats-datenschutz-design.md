# Umfragen: Erweiterte Statistik + Datenschutz-Flag

**Datum:** 2026-04-17
**Modul:** `modules/business/umfragen`
**Status:** Design genehmigt, bereit für Implementierungsplan

## Problem

Die aktuelle Ergebnis-Statistik hat zwei Lücken:

1. **Choices mit Text- oder Zahlen-Anhang werden falsch gerendert.** Antworten werden als `"Choice|||Text|||Zahl"` gespeichert (Radio) bzw. als JSON-Array solcher Strings (Checkbox). Die jetzige Bar-Chart-Logik in `admin-survey-results.php` vergleicht den gesamten String mit den Choice-Labels — dadurch erscheinen Freitext- und Zahlenanhänge als separate, fehlerhafte Balken.
2. **Kein Weg, sensible Fragen aus der öffentlichen Statistik fernzuhalten.** Der öffentliche Ergebnis-Link (`/umfrage-ergebnisse/<token>`) zeigt alle Fragen. Inhaltlich interne Fragen (z.B. interne Ratings, persönliche Einschätzungen) können nicht markiert und ausgefiltert werden.

## Ziele

- Auswahl-Choices mit Zahlen-Anhang: korrekte Aggregation + Darstellung der numerischen Werte (volle Stats inline, Detail beim Aufklappen).
- Auswahl-Choices mit Text-Anhang: Anzahl pro Option, Freitext-Einträge gruppiert unter der jeweiligen Option.
- Fragen lassen sich als "Datenschutzrelevant" markieren → hart gefiltert in der öffentlichen Ergebnisseite.
- Admin-Seite sieht weiterhin alles — mit visuellem Hinweis, welche Fragen öffentlich fehlen.

## Nicht-Ziele

- Keine Chart-Library. Reine HTML/CSS-Balken, `<details>/<summary>` für Expand.
- Kein Histogramm. Bei typischen Fallzahlen (<100) reicht sortierte Zahlenreihe.
- Kein Admin-Toggle für Datenschutz-Ansicht. Admin sieht immer alles — die öffentliche Sicht ist kein Preview-Modus.
- Kein Back-Migration der bisherigen Statistik-Fehldarstellung: die Korrektur wirkt ab Deploy für alle bestehenden Antworten.

## Datenmodell

Neue Spalte auf `dgptm_survey_questions`:

```sql
is_privacy_sensitive TINYINT(1) NOT NULL DEFAULT 0
```

**Migration:** Über `DGPTM_Survey_Installer::ensure_columns()` — der bestehende ALTER-TABLE-Fallback-Pfad für Post-dbDelta-Nachpflege. Default 0 → kein Daten-Backfill nötig.

**Persistenz:** `ajax_save_questions` nimmt das Flag entgegen und schreibt mit. Flag reist durch denselben Pfad wie `is_required`.

## UI — Question Editor

In beiden Editoren (`admin-survey-edit.php` + `templates/frontend-editor.php`):

- Neben der bestehenden "Pflichtfeld"-Checkbox eine zweite: **"Datenschutzrelevant (nicht öffentlich)"** mit 🔒-Icon.
- In der Fragen-Übersichts-Liste jeder Frage: Badge **"🔒 Datenschutzrelevant"** wenn Flag aktiv.

## UI — Statistik-Rendering

### Neue Helper-Methode

```
DGPTM_Survey_Admin::render_question_result($q, $q_answers, $choices)
```

Kapselt den gesamten Switch über `question_type`. Wird aufgerufen von:
- `admin-survey-results.php` (für alle Fragen)
- `public-results.php` (nur für Fragen mit `is_privacy_sensitive = 0`)

### Aggregation für Radio/Checkbox/Select

Einmaliges Parsing der gespeicherten `answer_value`-Strings in ein Dict:

```php
$per_choice = [
  'Choice A' => [
    'count'   => 47,
    'numbers' => [5, 3, 12, 5, 8, ...],   // nur wenn choices_with_number
    'texts'   => ['Freitext 1', ...],      // nur wenn choices_with_text oder __other__
  ],
  ...
]
```

Parser ignoriert `|||`-Trennung sauber — `Choice` wird für `$counts` verwendet, die Suffixe landen in `numbers`/`texts`.

### Render-Output pro Choice-Zeile

```
[Balken────────────] Choice-Label            47 (58%)
                     n=47  Median=5  Ø=6.2  Min–Max: 1–20  Σ=291   [▸ Details]
                     ├─ "Freitext 1"
                     ├─ "Freitext 2"
                     └─ ...
```

- **Balken** (bestehend): Häufigkeit, % vom Total.
- **Zahlen-Stats-Zeile**: nur bei `choices_with_number`, volle Stats (n, Median, Ø, Min–Max, Σ).
- **Freitext-Liste**: nur bei `choices_with_text` oder `__other__`, initial eingeklappt via `<details>`.
- **Expand-Panel** (`[▸ Details]`): sortierte Zahlenreihe + alle Freitexte.

### Datenschutz-Banner (nur Admin)

Vor dem Bar-Chart datenschutzrelevanter Fragen:

```
🔒 Datenschutzrelevant — in öffentlicher Ansicht ausgeblendet
```

Rotes Banner, kein Expand.

## UI — Public-Ergebnisseite

- Lädt Fragen mit `WHERE is_privacy_sensitive = 0`.
- Ausgeblendete Fragen erscheinen komplett nicht — kein Platzhalter, keine Lücken-Nummerierung im Fliesstext (Sort-Order-Lücken in der DB bleiben, wirken sich aber nicht auf die Ausgabe aus).
- Alle anderen Änderungen (neues Choice-Rendering) gelten hier genauso.

## Dateien, die geändert werden

| Datei | Änderung |
|---|---|
| `includes/class-survey-installer.php` | Spalte `is_privacy_sensitive` via `ensure_columns()`; Schema-Definition erweitern |
| `includes/class-survey-admin.php` | `render_question_result()` Helper; `ajax_save_questions` übernimmt Flag |
| `templates/admin-survey-edit.php` | Checkbox "Datenschutzrelevant" pro Frage |
| `templates/frontend-editor.php` | Checkbox + Badge in Fragen-Liste |
| `templates/admin-survey-results.php` | Delegation an Helper; Banner für sensible Fragen |
| `templates/public-results.php` | Filter in Fragen-Query; Delegation an Helper |
| `assets/css/admin.css` | Stats-Zeile, Freitext-Liste, Expand-Chevron, Banner |
| `assets/css/frontend.css` | analog für Public |
| `assets/js/admin.js` / `frontend-editor.js` | Checkbox-Persistenz (falls JS-Formsammler betroffen) |

## Kompatibilität

- Bestehende Antworten werden beim nächsten Statistik-Aufruf automatisch korrekt dargestellt (Parser ist rückwärtskompatibel — Choices ohne `|||`-Suffix bleiben unverändert).
- DB-Migration additiv: Default 0 → keine bestehende Frage wird ungewollt ausgeblendet.
- `DGPTM_UMFRAGEN_VERSION`-Bump triggert `ensure_tables` → Spalte kommt automatisch.

## Testing

- Manuelle Test-Umfrage mit mindestens einer Frage pro Typ: Radio+Zahl, Radio+Text, Checkbox+Zahl, Checkbox+Text, "__other__".
- Fragen mit/ohne Datenschutz-Flag mischen, Admin-Seite vs. Public-Link vergleichen.
- Regression: reine Radio/Checkbox ohne Suffix soll unverändert aussehen.
