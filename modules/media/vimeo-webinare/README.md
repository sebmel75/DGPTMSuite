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
