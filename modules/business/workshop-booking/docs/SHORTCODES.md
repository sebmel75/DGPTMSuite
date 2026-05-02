# Shortcodes — Workshop-Booking-Modul

Übersicht aller WordPress-Shortcodes, die das Modul `workshop-booking` bereitstellt — inklusive empfohlener Seiten-URL, Sichtbarkeit und Status.

## Übersicht

| Shortcode | Empfohlene Seite | Sichtbarkeit | Phase | Stand |
|---|---|---|---|---|
| `[dgptm_workshops]` | beliebig (z.B. `/veranstaltungen/`) | öffentlich | 1 | live |
| `[dgptm_workshops_success]` | `/buchung-bestaetigt/` | öffentlich (per Stripe-Redirect erreicht) | 1 | live |
| `[dgptm_workshop_ticket]` | `/veranstaltungen/ticket/` | per Token-Link | 2 | live |
| `[dgptm_workshop_entscheidungsvorlage]` | `/veranstaltungen/entscheidungsgrundlage/` | nur Mitglieder + Admin | 0 (intern) | live |
| `[dgptm_workshop_entscheidungsvorlage_export]` | beliebig (Admin-Seite) | nur Admin (`manage_options`) | 0 (intern) | live |

> **Hinweis:** Phase 0 = vor Phase-1-Implementierung — die Entscheidungsvorlage-Shortcodes wurden für die interne Abstimmung gebaut und sind unabhängig vom späteren Buchungsfluss.

## Details

### `[dgptm_workshops]`

**Empfohlene URL:** beliebig — Geschäftsstelle baut die Seite frei in Elementor o.ä. Beispiel: `https://perfusiologie.de/veranstaltungen/buchen/`

**Funktion:**
- Listet kommende Workshops und Webinare aus Zoho CRM (`DGfK_Events`) als Karten
- Filter: `Event_Type IN ('Workshop','Webinar')` + `From_Date >= heute`
- Cache: 5 Minuten (Transient)
- Karte enthält: Typ, Titel, Termin, „Jetzt buchen"-Button
- Klick auf Button öffnet `<dialog>` mit Vor-/Nachname + E-Mail-Eingabe
- Submit → AJAX `dgptm_wsb_book` → Booking_Service
- Bei kostenpflichtigen Tickets: Redirect zu Stripe-Checkout
- Bei Freitickets: direkt auf Bestätigungsseite

**Parameter:** keine

**Assets:** lädt automatisch `frontend.css` + `booking-form.js` (jQuery)

**Erforderliche Konfiguration:**
- Mindestens ein DGfK_Event mit `From_Date` in Zukunft
- Stripe-Test-Key in WP-Option `dgptm_wsb_stripe_secret_key`

---

### `[dgptm_workshops_success]`

**Empfohlene URL:** `https://perfusiologie.de/buchung-bestaetigt/`

**Funktion:**
- Statische Bestätigungsseite, die nach Stripe-Erfolg-Redirect angezeigt wird
- Enthält Hinweis auf Bestätigungs-Mail mit ICS- und Ticket-PDF-Anhang
- Kein dynamischer Inhalt — die eigentliche Bestätigung kommt per Mail

**Parameter:** keine

**URL-Parameter (informativ):**
- `?dgptm_wsb=success` — von Stripe gesetzt nach erfolgreicher Zahlung

**Hinweis:** Die Stripe-Success-URL ist konfigurierbar via Filter `dgptm_wsb_success_url`.

---

### `[dgptm_workshop_ticket]`

**Empfohlene URL:** `https://perfusiologie.de/veranstaltungen/ticket/`

**Funktion (Phase 2):**
- Token-basierter Ticket-Zugang für Nicht-Mitglieder (ohne WP-Login)
- Ohne `?dgptm_wsb_token=...` → Fehlermeldung „Ungültiger oder fehlender Zugangslink"
- Mit gültigem Token → zeigt:
  - Veranstaltungsname + Termin
  - Ticketnummer (Format `99999xxx`)
  - Status (`Angemeldet`, `Storniert`, …)
  - Button „Ticket-PDF herunterladen"
- Mit `?dgptm_wsb_pdf=<token>` → liefert PDF inline (`Content-Type: application/pdf`)

**URL-Parameter:**

| Parameter | Zweck |
|---|---|
| `dgptm_wsb_token` | Validiert Token, zeigt Ticket-Anzeige |
| `dgptm_wsb_pdf` | Validiert Token, sendet Ticket-PDF inline |

**Token-Eigenschaften:**
- 48 Hex-Zeichen (cryptographically secure)
- Scope `booking`
- Gültig bis Workshop-Ende + 30 Tage
- Widerrufbar (`revoked_at`)
- Use-Tracking via `record_usage()` (use_count + last_used_at)

**Filter:**
- `dgptm_wsb_ticket_page_url` — Standard-Page-URL ändern (Default `home_url('/veranstaltungen/ticket/')`)

**Wer bekommt einen Token-Link?**
- Buchende ohne WordPress-Account (Mail-Adresse nicht in `wp_users`)
- WP-User-Mitglieder bekommen stattdessen Mitgliederbereich-Zugang (Phase 3, noch nicht aktiv)

---

### `[dgptm_workshop_entscheidungsvorlage]`

**Empfohlene URL:** `https://perfusiologie.de/veranstaltungen/entscheidungsgrundlage/`

**Funktion:**
- Interne Entscheidungsvorlage zur Phase-1+2-Freigabe durch Vorstand und Geschäftsstelle
- Pro Abschnitt + pro Vorschlags-Zeile: Kommentare hinterlassen, „Vorschlag mittragen"
- Gesamt-Freigabe-Button am Ende
- Admin: Kommentare als „eingearbeitet" markieren, Beteiligten-Benachrichtigungs-Mail
- Phase-2-Update: Musterticket-Vorschau im Header (statisches HTML+SVG, kein echter QR)

**Sichtbarkeit:**
- Eingeloggte Nutzer:innen mit Rolle `mitglied` oder `administrator`
- Gäste sehen Hinweis „Bitte melde dich an"

**Parameter:** keine

**Status:** vor Phase-1-Implementierung gebaut, bleibt als Audit-Dokument live.

---

### `[dgptm_workshop_entscheidungsvorlage_export]`

**Empfohlene URL:** beliebige Admin-Seite

**Funktion:**
- Klartext-Export aller Kommentare + Freigaben aus der Entscheidungsvorlage
- Ein Klick → Klartext markiert + in Zwischenablage kopiert
- Gruppiert nach Sektion + Zeilen-Kommentaren

**Sichtbarkeit:** ausschließlich `manage_options` (Admin)

**Parameter:** keine

---

## URL-Mapping (Stand 02.05.2026)

| URL | Shortcode | Phase |
|---|---|---|
| `https://perfusiologie.de/veranstaltungen/` | `[dgptm_workshops]` (Empfehlung) | 1 |
| `https://perfusiologie.de/buchung-bestaetigt/` | `[dgptm_workshops_success]` | 1 |
| `https://perfusiologie.de/veranstaltungen/ticket/` | `[dgptm_workshop_ticket]` | 2 |
| `https://perfusiologie.de/veranstaltungen/entscheidungsgrundlage/` | `[dgptm_workshop_entscheidungsvorlage]` | 0 |

## Geplante Shortcodes (noch nicht implementiert)

| Shortcode | Phase | Zweck |
|---|---|---|
| `[dgptm_meine_tickets]` | 3 | Mitgliederbereich-Tab — alle Buchungen eines Mitglieds (Modul + Backstage gespiegelt) |
| `[dgptm_workshop_layout_editor]` | 4 | Token-basierter Layout-Editor für externe Designer:innen (Bescheinigungen) |

## AJAX-Endpoints (vom Modul registriert)

| Action | Auth | Zweck |
|---|---|---|
| `dgptm_wsb_book` | öffentlich (mit Nonce) | Buchungs-Submit aus Dialog → Booking_Service |
| `dgptm_wsb_evl_*` | eingeloggt | Entscheidungsvorlage-Kommentare/Freigaben (acht Endpoints) |

## REST-Routen

| Route | Methode | Zweck |
|---|---|---|
| `/wp-json/dgptm-workshop/v1/stripe-webhook` | POST | Stripe-Event-Empfang (mit HMAC-Signaturprüfung) |
