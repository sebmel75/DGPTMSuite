# Abstimmen-Addon Redesign — Design Spec

## Ziel

Ueberarbeitung des DGPTM Abstimmungs-Systems fuer Vereinsabstimmungen (50-200 Teilnehmer). Anonyme Stimmabgabe, Timer-Countdown, moderne Beamer-Ergebnisanzeige, konfigurierbare Mehrheitsregeln.

## Kernprinzipien

1. **Anonym aber dokumentiert:** Wer anwesend war und wer abgestimmt hat wird protokolliert. Wie jemand abgestimmt hat ist nicht rueckverfolgbar.
2. **Manager-gesteuert:** Der Manager kontrolliert den kompletten Ablauf — Frage starten, stoppen, Ergebnis freigeben.
3. **Timer optional:** Pro Frage konfigurierbar ob Auto-Close nach Ablauf oder manuelles Stoppen.
4. **Vereinsrecht-konform:** Quorum und Mehrheitsregeln konfigurierbar pro Frage.

---

## 1. Datenbank-Aenderungen

### Tabelle `dgptm_abstimmung_poll_questions` — Neue/geaenderte Spalten

| Spalte | Typ | Default | Beschreibung |
|--------|-----|---------|--------------|
| `time_limit` | INT | 0 | Sekunden. 0 = kein Timer. Existiert bereits, wird jetzt genutzt. |
| `auto_close` | TINYINT(1) | 0 | 1 = Frage wird automatisch geschlossen nach `time_limit`. 0 = Timer nur visuell. |
| `majority_type` | VARCHAR(20) | 'simple' | `simple` (>50%), `two_thirds` (>=66.7%), `absolute` (>50% aller Anwesenden) |
| `quorum` | INT | 0 | Mindest-Stimmenzahl. 0 = kein Quorum. |
| `started_at` | DATETIME | NULL | Server-Zeitpunkt des Aktivierens. Countdown wird daraus berechnet: `started_at + time_limit - now`. |

### Tabelle `dgptm_abstimmung_polls` — Neue Spalte

| Spalte | Typ | Default | Beschreibung |
|--------|-----|---------|--------------|
| `guest_voting` | TINYINT(1) | 1 | 0 = nur eingeloggte WP-User. 1 = auch Gaeste (QR + Name-Gate). |

### Tabelle `dgptm_abstimmung_votes` — Aenderung fuer Anonymitaet

Aktuelles Problem: `user_id` und `ip` in der Votes-Tabelle erlauben Rueckverfolgung.

**Loesung:** Bei anonymen Fragen (`is_anonymous=1`) werden Votes **ohne identifizierende Daten** gespeichert:
- `user_id` = 0
- `ip` = 'anonymous'

Stattdessen wird in einer separaten Tracking-Spalte der Participants-Tabelle dokumentiert ob jemand abgestimmt hat:

### Tabelle `dgptm_abstimmung_participants` — Neue Spalte

| Spalte | Typ | Default | Beschreibung |
|--------|-----|---------|--------------|
| `voted_questions` | TEXT | NULL | JSON-Array der Question-IDs bei denen diese Person abgestimmt hat. z.B. `[5,8,12]` |

So ist dokumentiert WER abgestimmt hat, aber nicht WIE.

---

## 2. Anonymes Abstimmen (Kernlogik)

### Ablauf bei `is_anonymous = 1`:

1. User waehlt seine Option(en) und klickt "Abstimmen"
2. Backend prueft: Hat dieser User/Cookie fuer diese Frage schon abgestimmt? → Check `voted_questions` JSON in Participants
3. Falls ja → Fehlermeldung "Sie haben bereits abgestimmt"
4. Falls nein:
   a. Vote wird eingefuegt mit `user_id=0, ip='anonymous'` (nicht rueckverfolgbar)
   b. Question-ID wird zum `voted_questions`-Array des Teilnehmers hinzugefuegt
   c. Erfolgsmeldung
5. **Kein Umaendern moeglich** bei anonymen Fragen (im Gegensatz zu nicht-anonymen)

### Ablauf bei `is_anonymous = 0` (wie bisher):

- Vote wird mit `user_id` gespeichert
- Umaendern moeglich (alte Stimme wird geloescht, neue eingefuegt)
- Manager kann Stimmen einzelnen Personen zuordnen

---

## 3. Timer & Auto-Close

### Konfiguration (im Manager-UI pro Frage):
- **Zeitlimit:** Eingabefeld in Sekunden (0 = kein Timer)
- **Auto-Close:** Checkbox "Automatisch schliessen nach Ablauf"

### Ablauf:

1. Manager aktiviert Frage → `started_at = NOW()` wird in DB gesetzt
2. Beamer-Payload liefert `remaining_seconds = started_at + time_limit - NOW()`
3. **Beamer:** Zeigt Countdown oben links (rot, pulsierend bei < 10s)
4. **Member-Vote:** Zeigt Countdown im Abstimmformular
5. Bei Ablauf:
   - **Auto-Close aktiv:** Server prueft bei jedem Payload-Request ob Zeit abgelaufen. Falls ja → Frage wird automatisch auf `status='stopped'` gesetzt, `ended=NOW()`.
   - **Auto-Close inaktiv:** Timer laeuft auf 0:00, Beamer zeigt "Zeit abgelaufen", aber Frage bleibt offen bis Manager manuell stoppt.
6. **Vote-Submission prueft ebenfalls:** Falls `auto_close=1` und Zeit abgelaufen → Vote wird abgelehnt.

### Ohne Timer (`time_limit = 0`):
- Beamer zeigt aktuelle Uhrzeit oben links (kein Countdown)
- Manager stoppt manuell

---

## 4. Beamer-Ansicht (Corporate Dark Design)

### Visuelles Design:
- **Hintergrund:** `#111827` (fast-schwarz)
- **Akzentleiste:** 4px am oberen Rand, Gradient `#2d6cdf → #06b6d4`
- **Schrift:** System-UI/Segoe UI, weiss
- **Font-Groessen:** Frage 28-34px, Prozentzahlen 36-48px

### Zustand 1: Wartemodus (idle)
- Uhr oben links: aktuelle Uhrzeit, weiss, 16px
- Poll-Name oben rechts, gedaempft
- Logo zentriert (aus Poll-Einstellungen)
- Konfigurierbarer Wartetext darunter
- QR-Code unten rechts (optional)

### Zustand 2: Aktive Abstimmung
- **Timer oben links:** Countdown in rot (`#f87171`), pulsierend bei < 10s. Oder Uhrzeit wenn kein Timer.
- Frage-Text gross zentriert (28-34px, fett)
- Fortschrittsbalken: `{abgestimmt} von {anwesend} Stimmen ({prozent}%)`
- Balken mit Gradient-Fuelllung
- QR-Code unten rechts
- **Keine Ergebnisse sichtbar** waehrend die Abstimmung laeuft

### Zustand 3: Warten auf Freigabe (Frage gestoppt, nicht freigegeben)
- "Abstimmung beendet — Ergebnis wird ausgewertet..."
- Uhr zeigt wieder Uhrzeit
- Dezente Animation (z.B. pulsierender Punkt)

### Zustand 4: Ergebnis-Karten (nach Freigabe durch Manager)
- Frage als Ueberschrift (20px)
- **Ergebnis-Karten** nebeneinander:
  - Jede Option als Karte mit farbigem Hintergrund (dezent, 12% Opacity)
  - Farbiger Rand passend zur Option
  - Grosse Prozentzahl (36-48px, fett, in Optionsfarbe)
  - Optionstext (14px)
  - Absolute Stimmenzahl (12px, gedaempft)
- **Farben:** Ja/Option 1 = Gruen (#4ade80), Nein/Option 2 = Rot (#f87171), Enthaltung/Option 3 = Gelb (#fbbf24), weitere = Blau (#60a5fa), Lila (#a78bfa)
- **Ergebnis-Text** unter den Karten:
  - Gruen Checkmark + "Angenommen" ODER Rot X + "Abgelehnt"
  - Mehrheitsregel + Gesamtstimmen + Quorum-Status
  - z.B. "✓ Angenommen (2/3-Mehrheit) · 87 Stimmen · Quorum erreicht"

### Zustand 5: Gesamtuebersicht (alle freigegebenen Fragen)
- Grid-Layout mit Ergebnis-Karten fuer jede Frage
- Kompaktere Version (kleinere Schrift)
- Poll-Name als Ueberschrift

### Polling-Intervalle:
- Aktive Abstimmung: 1000ms (fuer Countdown-Genauigkeit)
- Ergebnis/Idle: 3000ms
- Fehler: 5000ms mit Retry

---

## 5. Manager-UI ([manage_poll]) Aenderungen

### Frage erstellen/bearbeiten — Neue Felder:
- **Zeitlimit (Sekunden):** Input number, default 0. Hinweis "0 = kein Timer"
- **Auto-Close:** Checkbox, nur sichtbar wenn Zeitlimit > 0
- **Mehrheitsregel:** Dropdown: Einfache Mehrheit / 2/3-Mehrheit / Absolute Mehrheit
- **Quorum:** Input number, default 0. Hinweis "0 = kein Quorum"
- **Anonym:** Checkbox (existiert bereits als `is_anonymous`)

### Poll erstellen/bearbeiten — Neues Feld:
- **Gaeste erlauben:** Checkbox. Wenn aus: nur eingeloggte WP-User koennen abstimmen.

### Ergebnis-Freigabe:
- Nach Stoppen einer Frage: Button "Ergebnis auf Beamer freigeben"
- Klick setzt `results_released=1` und Beamer-State auf `results_one`
- Visuelles Feedback: Gruener Checkmark neben der Frage

### Anwesenheitsliste:
- Zeigt alle Teilnehmer mit:
  - Name
  - Beitrittszeit
  - Fuer welche Fragen abgestimmt (Checkmarks, ohne Wie)

---

## 6. Member-Vote ([member_vote]) Aenderungen

### Gaeste-Modus (`guest_voting=0`):
- Wenn nicht eingeloggt → "Bitte melden Sie sich an um abzustimmen" + Login-Link
- Kein Name-Gate, kein QR-Code Zugang

### Timer-Anzeige:
- Countdown prominent sichtbar im Abstimmformular
- Wenn < 10s: rot, pulsierend
- Bei Ablauf (auto_close): "Zeit abgelaufen" + Formular wird disabled
- Bei Ablauf (manuell): "Zeit abgelaufen — Abstimmung noch offen" (Abstimmen noch moeglich)

### Anonyme Abstimmung:
- Hinweis: "Diese Abstimmung ist anonym. Ihre Stimme kann nicht zu Ihnen zurueckverfolgt werden."
- **Kein Umaendern:** Button sagt "Abstimmen (endgueltig)" statt "Abstimmen"
- Nach Abgabe: "Ihre Stimme wurde anonym gezaehlt. ✓"

### Mehrheitsregel-Info:
- Unter der Frage: "Erforderlich: 2/3-Mehrheit · Quorum: 50 Stimmen"

---

## 7. Datei-Struktur (betroffene Dateien)

| Datei | Aenderungen |
|-------|-------------|
| `includes/common/install.php` | DB-Schema erweitern (neue Spalten) |
| `includes/common/helpers.php` | Majority-Auswertung Helper, Timer-Helper |
| `includes/ajax/vote.php` | Anonyme Vote-Logik, Timer-Pruefung, voted_questions Tracking |
| `includes/admin/manage-poll.php` | Neue Felder im UI (Timer, Majority, Quorum, Guest) |
| `includes/admin/admin-ajax.php` | CRUD fuer neue Felder, Auto-Close Logik |
| `includes/public/member-vote.php` | Timer-Anzeige, Anonym-Hinweise, Guest-Gate |
| `includes/beamer/view.php` | Komplett neu: Corporate Dark Design, Karten-Ergebnisse |
| `includes/beamer/payload.php` | Timer-Berechnung, Majority-Auswertung im Payload |
| `assets/css/frontend.css` | Timer-Styling, Anonym-Hinweise |
| `assets/js/frontend.js` | Countdown-Logik, Auto-Disable bei Ablauf |

---

## 8. Nicht in Scope

- Zoom-Integration (bleibt wie ist)
- Presence-Scanner (bleibt wie ist)
- Export CSV/PDF (bleibt wie ist, zeigt weiterhin anonymisierte Daten)
- Email-Registration (bleibt wie ist)
- Gewichtete Stimmen (nicht angefragt)
