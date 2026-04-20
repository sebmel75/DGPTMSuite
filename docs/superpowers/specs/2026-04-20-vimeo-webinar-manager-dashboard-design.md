# Vimeo-Webinare: Angleichung an Mitglieder-Dashboard (v2.0.0)

**Datum:** 2026-04-20
**Modul:** `modules/media/vimeo-webinare/`
**Ziel-Version:** v2.0.0 (Breaking durch strukturelle Shortcode-Änderungen)
**Status:** Freigegeben zur Planung

## Ziel

Das Modul `vimeo-webinare` wird visuell und strukturell an das `mitglieder-dashboard`
angeglichen. Der bisherige monolithische `[vimeo_webinar_manager]`-Shortcode wird in
drei klar getrennte Shortcodes zerlegt. Der Admin-Manager und die Statistiken werden
als Tabs im Mitglieder-Dashboard eingebunden, die öffentliche Webinar-Liste bleibt
eine eigenständige Frontend-Seite, erhält aber das Dashboard-Design.

Kein funktionaler Drop: sämtliche bestehenden Funktionen (Anlegen, Bearbeiten, Löschen,
Statistiken, Fortschrittsanzeige, Zertifikat, Vimeo-Player unter `/wissen/webinar/{id}`)
bleiben erhalten, werden teils neu orchestriert.

## Entscheidungen im Überblick

| # | Thema | Entscheidung |
|---|---|---|
| 1 | Scope | Visuell + strukturell; Manager und Statistiken als Dashboard-Tabs |
| 2 | Kanonisches Modul | `modules/media/vimeo-webinare/` → v2.0.0; `modules/content/vimeo-webinare/` wird gelöscht |
| 3 | Visibility | ACF-Feld `webinar` (Key `field_692a7cabb8041`, Label „Webinare", Gruppe „Berechtigungen"). Kein WP-Rollen-Check im Modul |
| 4 | Edit/Create | Inline-Editor statt Modal, ein Slot gleichzeitig |
| 5 | Shortcodes | `[vimeo_webinar_manager]` (Admin, Dashboard-Tab), `[vimeo_webinar_liste]` (Frontend, öffentlich), `[vimeo_webinar_statistiken]` (Admin, Dashboard-Tab, neu) |
| 6 | Frontend-Liste | Komplett an Dashboard-Design angepasst; Emojis durchgängig durch Dashicons ersetzt |
| 7 | Mobile | Tabellen klappen unter 768 px zu `.dgptm-card`-Layout |
| 8 | Löschen | Trash (`wp_trash_post`), kein Force-Delete |
| 9 | Statistiken | Gewichtetes Mittel für „Abschlussrate Ø" (`total_completed / total_views * 100`). Kein CSV, kein Chart.js |
| 10 | Interne Struktur | Repository-Klasse + drei Shortcode-Klassen; Trennung Daten/Rendering |

## Dateistruktur (Ziel)

```
modules/media/vimeo-webinare/
├── dgptm-vimeo-webinare.php              # Bootstrap (Konstanten, Klasse-Init, Hooks)
├── module.json                           # version: 2.0.0
├── README.md                             # neu, kompakt; löst Altdoku ab
├── CHANGELOG.md                          # fortgeschrieben
├── includes/
│   ├── class-vimeo-api.php               # bleibt
│   ├── class-webinar-repository.php      # NEU: zentrale Daten-/Stats-Zugriffe
│   ├── class-shortcode-manager.php       # NEU: [vimeo_webinar_manager] + AJAX
│   ├── class-shortcode-liste.php         # NEU: [vimeo_webinar_liste]
│   ├── class-shortcode-statistiken.php   # NEU: [vimeo_webinar_statistiken]
│   └── class-asset-loader.php            # NEU: Enqueue nur bei passendem Shortcode
├── templates/
│   ├── manager-liste.php                 # NEU: Liste + Inline-Editor-Slots
│   ├── manager-form.php                  # NEU: Inline-Formular
│   ├── liste.php                         # umgestaltet (Dashboard-Look)
│   ├── statistiken.php                   # NEU: Kennzahlen + Performance-Tabelle
│   └── player.php                        # bleibt (Player /wissen/webinar/{id})
└── assets/
    ├── css/
    │   ├── dashboard-integration.css     # Dashboard-Tokens garantieren
    │   ├── manager.css                   # Tabelle/Karten, Inline-Editor
    │   ├── liste.css                     # Karten-Grid im Dashboard-Look
    │   └── statistiken.css
    └── js/
        ├── manager.js                    # CRUD, Inline-Toggle, Mobile-Karten
        ├── liste.js                      # Filter + Suche
        └── statistiken.js                # Header-Sort (clientseitig)
```

Zu löschen: `modules/content/vimeo-webinare/` (komplett), sowie im Modul selbst die
Altdoku-Dateien `BATCH-IMPORT-ANLEITUNG.md`, `CHANGELOG-1.3.0.md`,
`DEBUG-COMPLETION.md`, `DEBUGGING.md`, `INSTALLATION.md`, `QUICK-REFERENCE.md`,
`QUICKSTART-V1.1.md`, `STRUCTURE.md`, `TESTING-GUIDE.md`, `UPDATE-V1.1.md`,
`UPDATE-V1.2.md`, `VERSION-1.2.4-SUMMARY.md`. Die alten Templates `manager.php`
und `admin-*.php` werden durch die neuen Templates ersetzt.

## Architektur

### Separation of Concerns

- **Repository** (`class-webinar-repository.php`): einziger Ort, der ACF-Felder liest,
  Posts schreibt und Stats aggregiert. Methoden:
  - `get_all(): array` — Liste aller Webinar-Posts samt ACF-Feldern, `status = publish`.
  - `get_stats_batch(array $ids): array` — ein gebündelter Query über User-Meta-Keys
    `_vw_progress_{id}` und `_vw_completed_{id}`. Rückgabe:
    `[webinar_id => ['completed' => int, 'in_progress' => int, 'total_views' => int]]`.
  - `save(array $data): int|WP_Error` — legt an oder aktualisiert; sanitized Input;
    gibt Post-ID oder `WP_Error` zurück.
  - `trash(int $id): bool` — `wp_trash_post`, kein Force-Delete.
  - `get_average_completion_rate(): float` — gewichtetes Mittel:
    `total_completed / total_views * 100`, 0.0 wenn `total_views == 0`.

- **Shortcode-Klassen**: kapseln je einen Shortcode, rufen Repository auf, laden
  Template. Keine ACF-Zugriffe, keine `ReflectionMethod`-Konstrukte, keine
  Singleton-Aufrufe im Template.

- **Templates**: reine Präsentation. Erhalten vorbereitete Arrays über `include` mit
  gesetzten lokalen Variablen. Keine DB-Zugriffe im Template.

- **Asset-Loader**: registriert auf `wp_enqueue_scripts` pro Seite:
  prüft `has_shortcode($post->post_content, ...)`, lädt `dashboard-integration.css`
  plus die shortcode-spezifischen Assets.

### Autorisierung

- **Konstante im Bootstrap**: `define('DGPTM_VW_PERMISSION_FIELD', 'webinar');`
- **Zentrale Methode** auf der Bootstrap-Klasse `DGPTM_Vimeo_Webinare`:
  ```php
  public function user_can_manage_webinars(): bool {
      if (!is_user_logged_in()) return false;
      return (bool) get_field(
          DGPTM_VW_PERMISSION_FIELD,
          'user_' . get_current_user_id()
      );
  }
  ```
- **Schreibende AJAX-Handler** (`dgptm_vw_save`, `dgptm_vw_delete`, `dgptm_vw_get_row`):
  Nonce-Check via `check_ajax_referer('dgptm_vw', 'nonce')`, dann
  `user_can_manage_webinars()`. Bei `false` → `wp_send_json_error('Keine Berechtigung')`.
- **Lesende AJAX-Handler** für Player-Fortschritt (`dgptm_vw_save_progress`) und
  Zertifikat-Generierung (`dgptm_vw_generate_certificate`): bleiben auf
  `is_user_logged_in()` — werden von allen Mitgliedern genutzt.
- **Keine Doppelprüfung im Shortcode-Render**: Sichtbarkeit des Tabs steuert das
  Dashboard-Modul anhand desselben ACF-Felds.

## Shortcode-Spezifikationen

### `[vimeo_webinar_manager]` — Admin, im Dashboard-Tab „Liste"

**Layout Desktop:**
- Toolbar oben: Suchfeld (Filter nach Titel, clientseitig) links; Button
  „Neues Webinar" rechts (`.dgptm-btn--primary`, Dashicon `dashicons-plus-alt`).
- Tabelle mit 6 Spalten: Titel, Vimeo-ID, EBCP-Punkte, Erforderlich %,
  Abgeschlossen, Aktionen.
- Aktionen als Icon-Buttons (Dashicons `dashicons-edit`, `dashicons-chart-bar`,
  `dashicons-visibility`, `dashicons-trash`). Farben: `--dd-muted` normal,
  `--dd-primary` bei Hover, `--dd-accent` für Löschen-Hover.
- Tabelle rahmt sich als `.dgptm-card`-ähnlicher Block (`--dd-border`, `--dd-radius`,
  `--dd-card`-Hintergrund).

**Inline-Editor:**
- Klick auf „Bearbeiten" → unter der Zeile erscheint `<tr><td colspan="6">…</td></tr>`
  mit dem Formular aus `manager-form.php`.
- Klick auf „Neues Webinar" → Formular erscheint als `.dgptm-card` oberhalb der
  Tabelle, Titel „Neues Webinar".
- Nur ein Editor-Slot gleichzeitig; wechseln erzeugt bei ungespeicherten Änderungen
  einen `confirm()`-Dialog.
- Formularfelder: Titel*, Beschreibung, Vimeo-ID*, Erforderlicher Fortschritt %*
  (1–100), EBCP-Punkte* (≥0, Schritte 0.5), VNR.
- Aktionen: `[Abbrechen]` (`.dgptm-btn--ghost`) · `[Speichern]` (`.dgptm-btn--primary`).
- Nach Erfolg: betroffene Zeile wird via `dgptm_vw_get_row` neu geladen, Editor klappt
  zu, Toast oben rechts (`.dgptm-toast.dgptm-toast--success`, 3 s auto-dismiss).

**Mobile (<768 px):**
- Tabelle wird pro Zeile zu `.dgptm-card` mit `<h3>`-Titel, `.dgptm-data-list` für
  Metadaten, Aktionen als Icon-Button-Reihe unten.
- Inline-Editor klappt zwischen den Karten auf.
- Toolbar bleibt sticky oben.

**AJAX-Endpoints** (neu benannt mit Präfix `dgptm_vw_*`; alte Handler des v1.3.1-Managers
werden durch diese ersetzt):
- `wp_ajax_dgptm_vw_save` — create (`post_id == 0`) oder update. Nonce + Permission.
- `wp_ajax_dgptm_vw_delete` — `trash()` im Repository. Nonce + Permission.
- `wp_ajax_dgptm_vw_get_row` — rendert eine Tabellenzeile neu (Post-HTML nach
  Save). Nonce + Permission.

Lesende Endpoints für Player/Fortschritt/Zertifikat behalten ihre bestehenden
Namen aus v1.3.1, damit der Player unverändert funktioniert.

### `[vimeo_webinar_statistiken]` — Admin, im Dashboard-Tab „Statistiken"

**Layout:**
- Kennzahlen-Grid oben (4 Karten nebeneinander, Desktop; 1-spaltig, Mobile):
  1. Gesamt Webinare
  2. Gesamt Abschlüsse
  3. In Bearbeitung
  4. Abschlussrate Ø (gewichtet)
- Jede Karte: große Zahl (`36px`, `--dd-primary`) + Label (`13px`, `--dd-muted`),
  `.dgptm-card`-Rahmen.
- Performance-Tabelle darunter: Webinar, Abgeschlossen, In Bearbeitung, Gesamt-Ansichten,
  Completion-Rate, Sparkline.
- Sparkline: reiner CSS-Balken (`.vw-sparkline`, `width: calc(var(--rate) * 1%)`,
  `background: --dd-primary`, Hintergrund `--dd-primary-light`).
- Header-Klick sortiert clientseitig (`statistiken.js`), keine AJAX-Roundtrips.

**Mobile:**
- Kennzahlen-Grid → 1 Spalte.
- Performance-Tabelle → Karten-Layout (wie beim Manager).

**Server-Seite:**
- Rein serverseitig gerendert, kein eigener AJAX.
- Nutzt `get_stats_batch()` und `get_average_completion_rate()` aus dem Repository.

### `[vimeo_webinar_liste]` — Frontend, öffentliche Seite

**Layout:**
- Optionaler Login-Banner oben (für nicht eingeloggte User), als `.dgptm-card` mit
  linkem Akzentstreifen `border-left: 3px solid var(--dd-accent)` + Dashicon
  `dashicons-info-outline`, Button `.dgptm-btn--primary` „Jetzt anmelden".
  Textanrede: **Sie** (öffentlich).
- Sektion „Zuletzt angesehen" (nur eingeloggt, max 5 Einträge) als `.dgptm-card` mit
  `<h3>` + Liste. Jeder Eintrag: Titel-Link, relatives Datum (Heute/Gestern/vor X
  Tagen/DD.MM.YYYY), rechts Mini-Fortschrittsbalken oder `.dgptm-badge--success`
  „Abgeschlossen".
- Filter-Leiste: Suchfeld (Titel-Suche) + Status-Dropdown (Alle, Noch nicht begonnen,
  In Bearbeitung, Abgeschlossen).
- Karten-Grid: `display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px`.
- Karte pro Webinar:
  - 16:9-Thumbnail (`aspect-ratio: 16/9`) von `https://vumbnail.com/{vimeoId}.jpg`.
  - Fallback: `background: var(--dd-primary-light)` + zentriertes
    `dashicons-format-video`.
  - Status-Badge oben rechts: `.dgptm-badge`-Varianten:
    - Nicht begonnen → `.dgptm-badge.dgptm-badge--muted` (Grau-Ton, neue Variante, siehe „Neue Komponenten")
    - In Bearbeitung → `.dgptm-badge--accent`
    - Abgeschlossen → `.dgptm-badge--success` mit Dashicon `dashicons-yes-alt`
  - Lokaler Fortschritt (Cookie, nicht eingeloggt): kleiner Dashicon
    `dashicons-smartphone` rechts neben dem Badge.
  - Kartenkörper: Titel (`<h3>`, 16px), Meta-Zeile mit `dashicons-star-filled`
    + EBCP-Punkte und `dashicons-clock` + erforderlichem %, optional
    Fortschrittsbalken `.dgptm-progress`, Exzerpt (20 Wörter),
    Button-Reihe: „Jetzt ansehen"/„Fortsetzen"/„Erneut ansehen" als
    `.dgptm-btn--primary`; „Zertifikat" als `.dgptm-btn--ghost` mit Dashicon
    `dashicons-awards` (nur wenn abgeschlossen und eingeloggt).

**Funktional unverändert:**
- Cookie-Fortschritt für anonyme User.
- Zertifikat-Download ruft bestehende `vw_generate_certificate`-Route.
- Status-Filter und Suche rein clientseitig.
- Link auf Webinar-Detail: `/wissen/webinar/{id}` (Rewrite-Regel des Moduls bleibt).

**Mobile (<600 px):**
- Grid → 1 Spalte.
- Filter sticky oben.
- Buttons full-width.

## Design-Tokens und Komponenten

Die Dashboard-Tokens aus `modules/business/mitglieder-dashboard/assets/css/dashboard.css`
werden per `dashboard-integration.css` im Modul redeklariert (identische Werte),
damit die Optik auch greift, wenn `[vimeo_webinar_liste]` auf einer Seite
eingebunden ist, die das Dashboard-Modul nicht lädt:

```css
:root {
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
}
```

Neue Komponenten im Modul, die es im Dashboard noch nicht gibt:

- `.dgptm-btn--ghost` — transparenter Hintergrund, `--dd-primary` Border und Text,
  Hover: `--dd-primary-light` Hintergrund.
- `.dgptm-badge--muted` — neutrales Badge (Grau-Ton), für „Nicht begonnen"-Status
  und vergleichbar defensive Markierungen.
- `.dgptm-toast` / `.dgptm-toast--success` — fix positioniertes Feedback oben rechts.
- `.dgptm-progress` — schmaler Fortschrittsbalken, Fill in `--dd-primary`,
  Hintergrund `--dd-primary-light`.
- `.vw-sparkline` — Mini-Bar in Stats-Tabelle.

**Emoji-Entsorgung** (durchgängig in allen drei Shortcodes):

| Alt (Emoji) | Neu (Dashicon) |
|---|---|
| 🎬 | `dashicons-format-video` |
| 📚 | (weg, oder `dashicons-format-video`) |
| 📜 | `dashicons-backup` |
| ⭐ | `dashicons-star-filled` |
| ⏱ | `dashicons-clock` |
| 📄 | `dashicons-awards` |
| 📱 | `dashicons-smartphone` |
| ✓ (in Badges) | `dashicons-yes-alt` |

## Einbindung ins Mitglieder-Dashboard

Einmalig im Dashboard-Admin zu konfigurieren:

1. Neuer Top-Tab „Webinar-Verwaltung"; Permission-Dropdown: `webinar`.
2. Zwei Folder-Tabs unter „Webinar-Verwaltung":
   - „Liste" — Content: `[vimeo_webinar_manager]`
   - „Statistiken" — Content: `[vimeo_webinar_statistiken]`
3. Die öffentliche Seite mit `[vimeo_webinar_liste]` bleibt unverändert eingebunden.

Das Dashboard cacht nicht (`rocket_cache_reject_uri` greift bereits für
`/interner-bereich/(.*)`).

## Migration

Reihenfolge beim Deploy:

1. **Vorbedingung**: Admin setzt bei sich selbst ACF-Feld `webinar = true`.
   Sonst sperrt man sich nach dem Deploy aus.
2. `modules/content/vimeo-webinare/` komplett löschen.
3. Neue Dateien/Klassen anlegen, Alt-Code portieren. Kein Feature-Drop.
4. Plugin-Header-Version in `dgptm-vimeo-webinare.php` auf `2.0.0`,
   `module.json` ebenfalls auf `2.0.0`.
5. Dashboard-Tabs wie oben beschrieben anlegen.
6. Bestehende Seite mit `[vimeo_webinar_manager]` (falls vorhanden): Shortcode
   daraus entfernen, Seite bei Bedarf per 301 auf den Dashboard-Bereich weiterleiten.

## Testing

Manuell, da kein Test-Framework im Projekt:

| Test | Ort | Erwartung |
|---|---|---|
| `[vimeo_webinar_liste]` öffentlich, nicht eingeloggt | Frontend-Seite | Login-Banner sichtbar; Karten-Grid ohne Historie |
| `[vimeo_webinar_liste]` eingeloggt mit Historie | Frontend-Seite | „Zuletzt angesehen" zeigt max 5 Einträge |
| Zertifikat-Download für abgeschlossene Webinare | Frontend-Seite | PDF-Download startet, kein JS-Fehler |
| Dashboard-Tab „Webinar-Verwaltung" bei `webinar=1` | Mitglieder-Dashboard | Tab sichtbar, beide Folder-Tabs laden |
| Dashboard-Tab bei `webinar=0` oder Feld leer | Mitglieder-Dashboard | Tab fehlt komplett |
| Manager: Webinar anlegen inline | Dashboard-Tab „Liste" | Zeile erscheint in Tabelle, Toast „Gespeichert" |
| Manager: Webinar bearbeiten inline | Dashboard-Tab „Liste" | Zeile klappt auf, Save aktualisiert Zeile live |
| Manager: Webinar in Trash verschieben | Dashboard + WP-Papierkorb | Zeile verschwindet, Post liegt im Papierkorb |
| Statistiken | Dashboard-Tab „Statistiken" | 4 Kennzahlen, Performance-Tabelle sortierbar |
| Mobile <768 px: Manager → Karten | DevTools | Kein horizontales Scrollen, Inline-Editor klappt zwischen Karten |
| Mobile <600 px: Liste → 1 Spalte | DevTools | Karten full-width, Buttons full-width |
| AJAX `dgptm_vw_save` von User ohne `webinar`-Flag | Postman/DevTools | `{"success":false,"data":"Keine Berechtigung"}` |
| Player `/wissen/webinar/{id}` | Frontend | Unverändert funktional, Abschluss erzeugt `fortbildung`-Post |

## Risiken

- **URL-Präfix**: Die content-Kopie nutzte `/webinar/{id}`, die media-Kopie nutzt
  `/wissen/webinar/{id}`. Vor dem Löschen prüfen, ob Live-Content auf `/webinar/…`
  verlinkt. Falls ja: Rewrite-Regel im Player-Code ergänzen oder Redirects setzen.
- **`fortbildung`-Post-Type**: Wird vom Player beim Abschluss erzeugt. Nichts an der
  Logik ändern, aber Regressionstest verpflichtend.
- **Reflection-Aufrufe entfallen**: `get_webinar_stats` wird von `private` zu
  `public` im Repository. Falls externer Code das per Reflection nutzt
  (unwahrscheinlich), bricht das.
- **Nonce-Lebensdauer**: Dashboard-Tabs werden per AJAX nachgeladen. Der
  Manager-Nonce `dgptm_vw` wird beim Haupt-Shortcode-Render erzeugt und muss auch
  nach Tab-Wechsel noch gültig sein. Strategie: Nonce im `data-nonce`-Attribut am
  Container, `manager.js` liest ihn dort (nicht über `wp_localize_script` auf der
  Seite).

## Offene Punkte (nicht blockierend für die Spec)

- Feinzeichnung der Inline-Editor-Animation (Slide-down ja/nein): im Implementation-Plan.
- Konkretes Mapping der Altdoku-Dateien zum gekürzten README.md: im Implementation-Plan.
- Tatsächliche Sortier-Default-Spalte in der Statistiken-Tabelle: im Implementation-Plan.
