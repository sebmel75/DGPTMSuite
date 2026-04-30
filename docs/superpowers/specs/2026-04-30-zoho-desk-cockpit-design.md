# Zoho Desk Cockpit — Design

**Datum:** 2026-04-30
**Modul-ID:** `zoho-desk-cockpit`
**Kategorie:** `business`
**Status:** Spec, vor Implementierung

## Ziel

Eingeloggten, vom Admin autorisierten DGPTM-Mitgliedern werden ausgewählte Zoho-Desk-Tickets im Mitgliederbereich übersichtlich angezeigt — mit Status, Blueprint-Bearbeitungsstand und Kommentaren. Erledigte Tickets der letzten 7 Tage erscheinen in einem eigenen Reiter. Whitelist/Blacklist-Filter im Admin-Bereich engen die Anzeige primär auf Anfragen aus der Geschäftsstelle ein, eigene Tickets des eingeloggten Users werden zusätzlich immer gezeigt.

## Nicht-Ziele

- Keine Bearbeitung von Tickets aus WordPress heraus (nur Lesezugriff).
- Kein eigenes Ticket-System / kein Ticket-Erstell-Workflow.
- Keine historische Persistenz älter als 7 Tage (Source-of-Truth bleibt Zoho Desk).
- Kein Replacement für die native Zoho-Desk-UI — Detail-Ansicht enthält Link „In Zoho Desk öffnen".

## Architektur (Approach 3 — Transient-Hybrid)

```
modules/business/zoho-desk-cockpit/
├── module.json
├── dgptm-zoho-desk-cockpit.php       Bootstrap, Singleton, Hooks
├── includes/
│   ├── class-desk-api.php             OAuth, Token-Refresh, REST-Client, SSRF-Schutz
│   ├── class-desk-filter.php          Whitelist/Blacklist + Mail-Match-Logik
│   ├── class-desk-cache.php           Transient-Wrapper (Pro-User-Keys)
│   ├── class-desk-shortcode.php       Frontend-Render + AJAX-Endpoints
│   ├── class-desk-admin.php           Settings, Authorisierung, OAuth-UI
│   └── class-desk-logger.php          Wrapper auf DGPTM_Logger
├── templates/
│   ├── frontend-cockpit.php           Tabs „Offen" / „Erledigt 7 Tage"
│   └── frontend-detail.php            Modal: Kommentare + Blueprint
├── assets/
│   ├── css/frontend.css               Umfragen-Designsprache, dgptm-fe-* Klassen
│   └── js/frontend.js                 Tab-Switch, Detail-Modal, Refresh
└── assets/admin/
    ├── admin.css
    └── admin.js
```

**Architektonisches Muster:** Singleton mit `class_exists`-Guard und `$GLOBALS`-Initialisierungs-Guard wie im Modul-Boilerplate (siehe `DGPTMSuite/CLAUDE.md`). Post-Type-Registrierung entfällt (keine eigenen CPTs).

## Datenfluss

### Listen-Pull (5-Min-Transient pro User × Filter-Hash)

1. User mit Capability `dgptm_desk_cockpit_view` ruft Seite mit Shortcode `[dgptm_desk_cockpit]` auf.
2. `Desk_Shortcode::render` prüft Capability + Login. Bei Fehlen → kompakte Hinweisbox.
3. Cache-Lookup via `Desk_Cache::get($user_id, $filter_hash)`.
4. Bei Cache-Miss → `Desk_API::list_tickets()` ruft zwei Endpunkte:
   - **Offen:** `GET /api/v1/tickets/search?status=Open,On Hold,Escalated&sortBy=-modifiedTime&limit=100&include=contacts,assignee`
   - **Erledigt 7 Tage:** `GET /api/v1/tickets/search?status=Closed&closedTimeRange={now-7d}_{now}&sortBy=-closedTime&limit=100&include=contacts,assignee`
5. Ergebnisse durch `Desk_Filter::is_visible($ticket, $user_email)` gepiped:
   - **Whitelist-Hit** (mind. eine Bedingung):
     - `contact.email == user_email` (eigene → höchste Prio)
     - Domain von `contact.email` ∈ Domain-Whitelist
     - `contact.email` ∈ Mail-Whitelist
   - **Blacklist-Override** (außer eigene Mail):
     - Local-Part von `contact.email` enthält ein Pattern aus Mail-Pattern-Blacklist
     - Domain von `contact.email` ∈ Domain-Blacklist
6. Gefilterte Listen werden gemeinsam mit Render-Timestamp im Transient abgelegt (TTL 300 s).
7. Render in Tabs „Offen" (Default) / „Erledigt (7 Tage)".

### Detail-Pull (60-Sek-Transient pro Ticket-ID)

1. Klick auf Ticket → AJAX `dgptm_desk_get_ticket` mit Nonce + Ticket-ID.
2. Server-Schritte:
   - Capability-Check `dgptm_desk_cockpit_view`.
   - Ticket via `Desk_API::get_ticket($id)` holen, durch `Desk_Filter::is_visible` jagen — **falls nicht sichtbar: 403** (verhindert IDOR).
   - Parallel-Pulls: `GET /tickets/{id}/comments?limit=100&sortBy=-commentedTime` + `GET /tickets/{id}/getBlueprint`.
3. Render-Payload (HTML-Snippet) ans Frontend, Modal füllt sich.

### Token-Refresh-Cron

- Stündlicher WP-Cron `dgptm_desk_token_refresh` prüft Ablaufzeit, erneuert Access-Token vor Ablauf (Refresh-Token bleibt langlebig).
- Bei Refresh-Fehler: Admin-Notice + Logger-Eintrag (Level `error`).

## Filter-Logik (`Desk_Filter::is_visible`)

```
function is_visible($ticket, $user_email) {
    $contact_email = $ticket['contact']['email'] ?? '';
    if (!$contact_email) return false;

    $local = local_part($contact_email);
    $domain = domain_part($contact_email);

    // Whitelist
    $whitelist_hit_self    = strcasecmp($contact_email, $user_email) === 0;
    $whitelist_hit_domain  = in_array_ci($domain, $domain_whitelist);
    $whitelist_hit_mail    = in_array_ci($contact_email, $mail_whitelist);
    $whitelist_hit         = $whitelist_hit_self || $whitelist_hit_domain || $whitelist_hit_mail;

    if (!$whitelist_hit) return false;

    // Blacklist (Override, außer für eigene Mail)
    if ($whitelist_hit_self) return true;

    foreach ($mail_pattern_blacklist as $pat) {
        if (stripos($local, $pat) !== false) return false;
    }
    if (in_array_ci($domain, $domain_blacklist)) return false;

    return true;
}
```

**Default-Mail-Pattern-Blacklist:** `noreply`, `no-reply`, `mailer-daemon`, `bounce`, `do-not-reply`, `postmaster`, `notifications`, `automated`

**Default-Domain-Blacklist:** leer (Admin pflegt bei Bedarf)

## Frontend (Shortcode `[dgptm_desk_cockpit]`)

### Layout

- **Header-Leiste:** Anzahl offener Anfragen (Badge), letzter Sync-Zeitpunkt, Button „Aktualisieren" (löscht User-Transient).
- **Tabs** (`dgptm-fe-tabs`):
  - „Offen" (Default)
  - „Erledigt (7 Tage)"
- **Liste pro Tab** als Karten oder Tabelle (responsive: Tabelle ≥ 768 px, Karten darunter):
  - Spalten: Ticket-Nr (`ticketNumber`), Betreff (`subject`), Fragesteller (`contact.firstName lastName <email>`), Status-Badge (Farbe nach `status`-Mapping), Blueprint-Stage (`blueprint.currentStage` oder „—"), letzte Änderung (`modifiedTime`, relativ formatiert).
- **Klick auf Zeile** → Detail-Modal (`dgptm-fe-modal`):
  - Vollständiger Beschreibungstext (`description`, sanitized HTML)
  - Blueprint-Block: aktueller Schritt + Pfad (Vor- und nächste Schritte sofern aus API verfügbar)
  - Kommentar-Timeline: Autor, Zeit, Inhalt, Marker `public`/`private` (private werden angezeigt — Cockpit ist Vertrauenskontext)
  - Footer: Link „In Zoho Desk öffnen" (`webUrl` aus API), „Schließen"

### Designsprache (verbindlich)

- Strikt das Look-and-Feel des Umfragen-Moduls: Tokens `--dgptm-primary`, `--dgptm-radius`, `--dgptm-shadow` etc.
- Buttons ausschließlich `dgptm-fe-btn` (User-Memory: keine klobigen Custom-Buttons).
- Klassen-Präfix: `dgptm-desk-*`.
- CSS wird mit eigenem Handle `dgptm-desk-cockpit-frontend` registriert; Dependency auf `dgptm-umfragen-frontend` falls vorhanden, sonst eigene Token-Definitionen am Anfang der `frontend.css`.
- Keine Elementor-/Theme-Vererbung.

### Empty- und Error-States

- **Nicht eingeloggt:** „Bitte einloggen, um deine Anfragen zu sehen."
- **Eingeloggt, nicht autorisiert:** „Dieser Bereich ist nur für autorisierte Mitglieder. Bei Fragen wende dich an die Geschäftsstelle."
- **Keine Tickets sichtbar:** „Aktuell sind keine Anfragen für dich sichtbar." (mit kurzer Erklärung der Filter-Logik)
- **Desk-API-Fehler:** Stale-Transient (bis 2× TTL = 10 Min) ausliefern + Banner „Daten könnten veraltet sein". Wenn auch kein Stale verfügbar: Fehlerkasten „Verbindung zu Zoho Desk unterbrochen, bitte später erneut versuchen."

## Admin-Bereich

WP-Admin → DGPTM-Suite → Submenu **„Desk Cockpit"** (Capability `manage_options`).

### Tab „OAuth & Verbindung"

- Felder: Region (Dropdown EU/COM/IN/AU/JP), Org-ID, Client-ID, Client-Secret (verschlüsselt mit AUTH_KEY abgelegt).
- Button „Mit Zoho verbinden" → OAuth-Authorize-Flow → Redirect-URI auf `admin-ajax.php?action=dgptm_desk_oauth_callback`.
- Status-Anzeige: Token-Status (gültig/abgelaufen/fehlt), letzte Refresh-Zeit.
- Test-Button: „Verbindung testen" → pullt 1 Ticket, zeigt Erfolg/Fehler.

### Tab „Berechtigte Mitglieder"

- Sucheingabe (AJAX-User-Suche) + Multi-Select.
- Gespeichert als User-Meta `dgptm_desk_cockpit_authorized = 1`.
- Capability `dgptm_desk_cockpit_view` wird via `user_has_cap`-Filter dynamisch ergänzt für autorisierte User.
- Liste aktuell autorisierter User mit „Entfernen"-Button.

### Tab „Filter"

- **Whitelist Domains** (Textarea, eine pro Zeile, ohne `@`)
- **Whitelist E-Mails** (Textarea, eine pro Zeile)
- **Blacklist Mail-Pattern** (Textarea, Local-Part-Substrings, Defaults vorgefüllt; Reset-Button setzt auf Defaults zurück)
- **Blacklist Domains** (Textarea)
- Live-Test: Button „Filter testen" → pullt aktuelle Ticket-Listen (ohne User-Match), zählt sichtbar/ausgeblendet, zeigt Beispiele für ausgeblendete Mails (gut für Blacklist-Tuning).

## OAuth & Sicherheit

- **Eigenständiges Self-Client** für Zoho Desk (separat von `crm-abruf`).
- **Scopes:** `Desk.tickets.READ`, `Desk.contacts.READ`, `Desk.basic.READ`, `Desk.search.READ`.
- **Refresh-Token-Speicherung:** AES-verschlüsselt mit `AUTH_KEY`, abgelegt in `wp_options` (`dgptm_desk_oauth_refresh_token`).
- **Access-Token:** Transient mit TTL = `expires_in - 60s`.
- **Nonce-Prüfung** auf allen AJAX-Endpoints (`dgptm_desk_nonce`).
- **Capability-Check** `dgptm_desk_cockpit_view` an jedem Frontend-Endpoint, `manage_options` an jedem Admin-Endpoint.
- **SSRF-Schutz:** Wiederverwendung der Helfer aus `crm-abruf` (`dgptm_is_private_ipv4` etc.) — Aufruf-Hostname muss `*.zoho*.com|eu` matchen.
- **Logging:** `DGPTM_Logger::log` mit `module_id = 'zoho-desk-cockpit'`. Sensible Daten (Tokens, Secrets, Mailadressen in Debug-Logs) per `dgptm_redact_array` redacted.
- **IDOR-Schutz Detail-Endpoint:** Detail-Pull läuft durch denselben Filter wie Listing — User kann nicht via direkter Ticket-ID auf nicht zugewiesene Tickets zugreifen.
- **Rate-Limit-Schutz:** Beim Listing pro User max. 1 Live-Pull / 5 Min (Transient erzwingt das); manueller „Aktualisieren"-Button bricht den Cache eines Users, ist aber nutzergebunden — keine Org-weite API-Flut.

## Edge Cases

- **Token abgelaufen, Refresh schlägt fehl:** Admin-Notice in WP-Admin + Logger-Eintrag (Level `error`); Frontend zeigt Fehler-State.
- **Desk-API-Limit (200/min/Org):** Stale-Cache bevorzugen, sonst Hinweis. 429-Response wird mit exponentiellem Backoff im API-Client behandelt (max. 3 Retries).
- **Kein Blueprint am Ticket:** Stage-Spalte zeigt „—".
- **Mitglied ohne Whitelist-Treffer + ohne eigene Tickets:** Leer-State.
- **User-E-Mail-Wechsel (`profile_update`):** Hook leert User-Transients dieses Users.
- **Modul wird deaktiviert:** Transients werden bei `deactivation_hook` gelöscht; OAuth-Tokens bleiben (Re-Aktivierung soll funktionieren ohne Re-Connect).
- **Filter-Settings ändern:** Beim Speichern werden alle User-Listing-Transients invalidiert (`Desk_Cache::flush_all_listings`).

## Modul-Metadaten (`module.json`)

```json
{
  "id": "zoho-desk-cockpit",
  "name": "DGPTM - Zoho Desk Cockpit",
  "description": "Zeigt eingeloggten Mitgliedern Zoho-Desk-Tickets mit Blueprint-Status und Kommentaren. Whitelist/Blacklist-Filter im Admin.",
  "version": "1.0.0",
  "author": "Sebastian Melzer",
  "main_file": "dgptm-zoho-desk-cockpit.php",
  "dependencies": [],
  "optional_dependencies": ["crm-abruf"],
  "wp_dependencies": { "plugins": [] },
  "requires_php": "7.4",
  "requires_wp": "5.8",
  "category": "business",
  "icon": "dashicons-format-chat",
  "active": false,
  "can_export": true,
  "critical": false
}
```

## Festgelegte Mini-Entscheidungen

- **Private Desk-Kommentare:** werden angezeigt (Cockpit ist Vertrauenskontext, autorisierte Mitglieder sehen alles).
- **Erledigt-Reiter-Zeitraum:** 7 Tage hart codiert (kein Admin-Setting).
- **Pagination:** 50 Tickets pro Tab im Default-Render, Button „Mehr laden" für die nächsten 50 (max. 200 pro Tab insgesamt; Live-Pull holt einmalig 100 pro Status, Erweiterung via Folge-Request mit `from`-Parameter).
- **Default-Region:** EU (`desk.zoho.eu`).
- **Desk-API-Version:** v1.

## Akzeptanzkriterien

1. Modul lässt sich aktivieren und deaktivieren ohne Fatal Errors; nach Aktivierung erscheint die Submenu-Page.
2. OAuth-Flow funktioniert: nach „Mit Zoho verbinden" wird ein gültiger Refresh-Token gespeichert, „Verbindung testen" liefert Erfolg.
3. Shortcode auf einer geschützten Seite zeigt einem autorisierten User die korrekten Tickets gemäß Whitelist/Blacklist + eigene Mails.
4. Detail-Modal zeigt Kommentare und (sofern vorhanden) Blueprint-Stage.
5. Erledigte Tickets der letzten 7 Tage erscheinen im zweiten Tab.
6. Nicht-autorisierte User sehen die Cockpit-Inhalte nicht (auch nicht über direkten AJAX-Call).
7. Filter-Änderung im Admin invalidiert sofort alle User-Caches.
8. Frontend folgt Umfragen-Designsprache (Token-Inspektion via Browser-DevTools möglich).
9. Bei Desk-Ausfall werden Stale-Daten ausgeliefert mit Hinweis-Banner.

## Offene Punkte für Implementierungsplan

- Reihenfolge der Sub-Tasks (OAuth-Setup vor Frontend, oder Frontend mit Mock-Daten parallel?)
- Konkrete Status-Farben-Mapping (Open = grau, On Hold = orange, Escalated = rot, Closed = grün) — sollte mit Desk-Status-Setup der DGPTM-Instanz abgeglichen werden vor dem Build.
- Test-Strategie: manuelle WP-Admin-Tests (kein Test-Framework im Projekt) — Checkliste im Plan festhalten.
