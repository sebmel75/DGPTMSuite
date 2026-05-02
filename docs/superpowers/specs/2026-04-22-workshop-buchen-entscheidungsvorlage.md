# Workshop & Webinar-Buchung — Entscheidungsvorlage (Implementierungs-Spec)

**Erstellt:** 22.04.2026
**Letzte Aktualisierung:** 30.04.2026 (nach 5. Review-Runde, AGB-Abschluss)
**Status:** Design-Eckpunkte abgestimmt, Implementierung kann nach finaler Vorstands-Freigabe starten
**Empfänger:in:** Vorstand, Geschäftsstelle, Kursleitungen, AG-Leitungen
**Modul:** `modules/business/workshop-booking` (v0.2.5 → v1.0.0)
**Quelle der Wahrheit für Inhalte:** `modules/business/workshop-booking/templates/entscheidungsvorlage-dokument.php`

---

## 1. Ziel und Geltungsbereich

Neues DGPTMSuite-Modul **`workshop-booking`**, das **Workshops und Webinare** aus Zoho Backstage auslagert und direkt über die DGPTM-Webseite (perfusiologie.de) buchbar macht. Buchung läuft kostenlos (Freiticket) oder per Stripe-Zahlung. Jede Buchung erzeugt einen Eintrag im CRM-Modul `Veranstal_X_Contacts`.

**V1-Geltungsbereich (festgelegt 29.04.2026):**

| Im Scope | Out of Scope (V1) |
|---|---|
| Workshops | Kongresse → bleiben in Zoho Backstage |
| Webinare (Live + On-Demand-Nachholung) | Sachkundekurse → bleiben in Zoho Backstage |
| Mitgliederbereich „Meine Tickets" mit Backstage-Spiegelung | Native App (V1: Web-Tool, mobile-Browser) |
| Mehrsprachigkeit DE/EN | Massenmigration historischer Backstage-Buchungen |
| QR-Code-Tickets, automatische Teilnahmebescheinigungen | EFN-Erfassung im Buchungsformular für Nicht-Mitglieder |

**Hauptmotivation:**
- Entkopplung kleiner Veranstaltungen von Zoho Backstage (Aufwand pro Workshop dort gleich groß wie für einen Kongress)
- Eigenverantwortung der Arbeitsgemeinschaften (Geschäftsstelle prüft + gibt frei, bevor Buchung öffentlich)
- Einheitliche Sicht für Teilnehmer:innen (alle Tickets im Mitgliederbereich, egal ob Backstage- oder Modul-Buchung)

## 2. Ausgangslage und Wiederverwendung

### 2.1 Bestandsmodule, deren Funktionalität direkt eingebunden wird

| Bestandsmodul | Pfad | Was übernommen wird |
|---|---|---|
| **vimeo-webinare** | `modules/media/vimeo-webinare/includes/class-certificate-presets.php` | PDF-Engine für Teilnahmebescheinigungen mit Layout-Presets — keine zweite Engine |
| **vimeo-webinare** | `modules/media/vimeo-webinare/includes/class-vimeo-api.php` | Vimeo-Anbindung für Aufzeichnungs-Verteiler + Anti-Skip-Tracking für On-Demand-Nachholung |
| **stipendium** | `modules/business/stipendium/includes/class-gutachter-token.php` | Token-Logik für externe Zugänge (Nicht-Mitglieder, Designer:innen) — zeitlich begrenzt, widerrufbar |
| **stipendium** | `modules/business/stipendium/includes/class-token-installer.php` | Tabellen-Schema für Token-Verwaltung (Vorbild für `wp_dgptm_workshop_tokens`) |
| **stipendium** | `modules/business/stipendium/includes/class-mail-templates.php` | Pattern für transactional HTML-Mails mit DGPTM-Header (#003366) |
| **anwesenheitsscanner** | `modules/business/anwesenheitsscanner/anwesenheitsscanner.php` (3843 Zeilen) | QR-Code-Scanner-Webtool für Mobile (Browser, kein App-Store) — Erweiterung um Workshop-Tickets |
| **zoho-books-integration** | `modules/business/zoho-books-integration/dgptm-zoho-books.php` | Zoho-Books-API-Layer (Rechnungs-Erstellung direkt in Books, nicht über CRM) |
| **edugrant** | `modules/business/edugrant/templates/events-list.php` | Karten-UI für Event-Listing aus `DGfK_Events` als Blaupause |
| **edugrant** | `modules/business/edugrant/templates/user-grants.php` | Anzeige im Mitgliederbereich als Vorbild für „Meine Tickets" |
| **mitglieder-dashboard** | `modules/business/mitglieder-dashboard/` | Tab-Integration für „Meine Tickets" |
| **crm-abruf** | `modules/core-infrastructure/crm-abruf/` | Zentraler Zoho-CRM-API-Layer; 4-Felder-E-Mail-Suche (Email, Secondary_Email, Third_Email, Fourth_Email via COQL) |
| **fortbildung** | `modules/business/fortbildung/` | Existierender Post-Type für FoBi-Punkte; das Modul triggert Eintrag analog Webinar-Workflow |

### 2.2 Bewusst nicht übernommen

| Modul | Warum nicht |
|---|---|
| `stripe-formidable` | An Formidable Forms gekoppelt; eigene Stripe-Checkout-Session ist sauberer und entkoppelt |
| Eigene QR-Scanner-Implementierung | `anwesenheitsscanner` existiert bereits in v2.0 mit PDF + Barcode-Generierung |

## 3. Getroffene Design-Entscheidungen (Stand 30.04.2026, AGB-Abschluss)

### 3.1 Buchungs-Logik (Vorschläge 1–12a)

| # | Entscheidungspunkt | Gewählt | Quelle |
|---|---|---|---|
| 1 | Modul-Scope V1 | **Workshops + Webinare**; Kongresse/Sachkundekurse weiter in Backstage | EVL §3 Vorschlag 1 |
| 2 | User-Authentifizierung | Hybrid: Mitglieder one-click, Gäste per Formular | EVL §3 Vorschlag 2 |
| 3 | Bezahl-Integration | **Eigenes Stripe-Unterkonto** für Workshops/Webinare; Stripe Checkout (hosted); Karte/SEPA/Apple Pay/PayPal über Stripe | EVL §3 Vorschlag 3 |
| 4 | Tickets pro Buchung | Mehrere; **pro Person ein `Veranstal_X_Contacts`-Eintrag**; optional Sammel-Rechnung an Zahler mit Kostenstelle/Auftragsnummer | EVL §3 Vorschlag 4 + offene Frage 13 |
| 5 | Pflichtfelder | Vor-/Nachname, E-Mail, **Rechnungsadresse**, **Studierendenstatus** (bei reduziertem Ticket); Stamm-/Rechnungsdaten aus CRM werden vor Buchungsabschluss zur Bestätigung/Aktualisierung angezeigt; Studierendenstatus nie automatisch übernommen | EVL §3 Vorschlag 5 + 18 |
| 5a | Studi-Nachweis | Pflicht-Upload Immatrikulationsbescheinigung im Buchungsformular; Geschäftsstelle prüft, bei Ablehnung Differenz-Nachforderung | EVL offene Frage 17 |
| 6 | Kontakt-Matching | 4-Felder-E-Mail-Suche (`Email`, `Secondary_Email`, `Third_Email`, `Fourth_Email` via COQL-Fallback aus `crm-abruf`); kein Treffer → Contact-Neuanlage | EVL §3 Vorschlag 6 |
| 7 | UI-Integration | Shortcodes `[dgptm_workshops]` (Liste/Detail/Formular), `[dgptm_workshops_success]` (Bestätigung), `[dgptm_meine_tickets]` (Mitgliederbereich) | EVL §3 Vorschlag 7 |
| 8 | Kapazität | Hartes Limit + automatische FIFO-**Warteliste** mit 24-h-Nachrück-Frist | EVL §3 Vorschlag 8 |
| 9 | Storno (AGB-konform) | **Zwei Optionen, AGB §6:** (a) Stornierung bis Frist (Default 42 Tage / 6 Wochen, einstellbar 28–42 d); Erstattung automatisch über Stripe abzüglich **10 % / max. 35 €**; nach Frist gesperrt (Härtefall über Geschäftsstelle). (b) **Übertragung auf Ersatzteilnehmer:in** bis drittletzten Werktag vor Beginn; Bearbeitungsgebühr **20 % / max. 70 €**; bei Warteliste vorrangig Wartelisten-Plätze | EVL §3 Vorschlag 9 + offene Frage 1, 2, 16 |
| 10 | E-Mails | Transactional aus dem Modul: Bestätigung (mit Ticket-PDF + ICS), Warteliste, Nachrücker, Storno, Termin-Verlegung; **Reminder 7 Tage und 1 Tag vor Workshop-Start direkt aus Modul**; Marketing weiter über Zoho MA | EVL §3 Vorschlag 10 |
| 11 | Promo-/Rabattcodes | **In V1 nicht aktiv.** Vorbereitet: Pflege zentral im Modul/CRM (nicht bei Stripe), für spätere Wiederverwendung | EVL §3 Vorschlag 11 |
| 12 | Architektur-Ansatz | Eigenständiges Modul mit Service-Interfaces (`EventSource`, `PaymentGateway`, `BookingWriter`, `MailSender`, `WaitlistStore`, `InvoiceGateway`, `CertificateRenderer`) | EVL §3 Vorschlag 12 |
| 12a | **Mehrsprachigkeit (DE/EN)** | Frontend, Buchungsformular, Bestätigungsmails, Teilnahmebescheinigung sind von Anfang an zweisprachig vorbereitet; pro Workshop Sprache wählbar; bei englischsprachigen Veranstaltungen automatisch englische Texte | EVL §3 Vorschlag 12a |

### 3.2 Tickets, QR-Code, Mitgliederbereich (Vorschläge 13–18)

| # | Entscheidungspunkt | Gewählt | Quelle |
|---|---|---|---|
| 13 | Backstage-Verbindung | **Klargestellt 30.04.2026 nachmittags:** Backstage schreibt bereits über bestehende Zoho-Flows direkt in `Veranstal_X_Contacts` — keine eigene Spiegelung nötig. Diese Einträge tragen `Quelle ≠ 'Modul'` und werden vom Sync_Coordinator konsequent geskippt; im Mitgliederbereich aber read-only mit Hinweis „Verwaltung über Backstage" angezeigt | EVL §3 Vorschlag 13 + offene Frage 5, 6 |
| 14 | Ticketnummer | Backstage-Format mit **Präfix „99999"** für Modul-Tickets; eindeutige Unterscheidung; QR-Scan funktioniert in beiden Systemen | EVL §3 Vorschlag 14 + offene Frage 7 |
| 15 | QR-Code | Jedes Ticket trägt QR mit Ticketnummer; Scan via `anwesenheitsscanner` (Web-Tool, mobile Browser) | EVL §3 Vorschlag 15 |
| 16 | Mitgliederbereich „Meine Tickets" | Eingeloggte Mitglieder sehen alle Buchungen (Modul + Backstage) mit Termin, Status, QR-Code, Storno-Möglichkeit; **Bescheinigung erst nach bestätigter Anwesenheit + Veranstaltungsende downloadbar** | EVL §3 Vorschlag 16 |
| 17 | Zugang für Nicht-Mitglieder | Persönlicher Link per E-Mail (Token-Logik aus `stipendium`); gültig **bis Workshop-Ende plus 30 Tage** | EVL §3 Vorschlag 17 + offene Frage 8 |
| 18 | Auto-Zuordnung E-Mail | Bei Buchung ohne Login wird E-Mail mit CRM abgeglichen; Treffer → automatische Zuordnung; vor Buchungsabschluss Stamm-/Rechnungsdaten zur Bestätigung anzeigen, Änderungen fließen zurück; **Studierendenstatus immer neu bestätigen** | EVL §3 Vorschlag 18 |

### 3.3 Teilnahmezertifikate (Vorschläge 19–21)

| # | Entscheidungspunkt | Gewählt | Quelle |
|---|---|---|---|
| 19 | Trigger Bescheinigung | **Erst nach Workshop-Ende UND bestätigter Anwesenheit**; Geschäftsstelle/Kursleitung markiert anwesend im CRM; Modul nutzt **`vimeo-webinare/class-certificate-presets.php`** als Engine | EVL §3 Vorschlag 19 + offene Frage 11 |
| 19a | Anwesenheits-Definition | Präsenz: QR-Scan (Einlass + optional Ausgang); ≤ 10 % Frühausstieg unkritisch. Online-Live: Daten aus Live-Tool (Zoho Meetings) — automatisch via System abgeglichen über E-Mail; Schwelle **frei pro Workshop konfigurierbar** (CRM-Feld). Online On-Demand: Anti-Skip-Tracking aus `vimeo-webinare`. Manuelle Korrektur durch Kursleitung jederzeit möglich | EVL §3 Vorschlag 19 + offene Frage 11, 18 |
| 20 | Layout konfigurierbar | Pro Workshop: Standard-Layout oder eigenes Layout zuweisbar (Standard wird im Implementierungsverlauf mit Geschäftsstelle entwickelt) | EVL §3 Vorschlag 20 + offene Frage 10 |
| 21 | Externe Designer:innen | Token-Link per E-Mail (Vorbild: `stipendium/class-gutachter-token.php`); Layout-Editor im Browser (Hintergrundbild, Logo, Texte, Schriften, Live-Vorschau); Link **14 Tage gültig** (jederzeit widerrufbar); Designer sieht **nur dieses eine Layout**, keine Buchungen, keine TN-Daten; **nur Geschäftsstelle darf einladen** | EVL §3 Vorschlag 21 + offene Frage 9 |

### 3.4 Erweiterte Funktionen (Vorschläge 22–23)

| # | Entscheidungspunkt | Gewählt | Quelle |
|---|---|---|---|
| 22 | Variable Pflichtfelder pro Ticket-Typ | Pro Ticket-Typ Zusatzfelder definierbar (Sponsoren: Firmenname/USt-ID; Ehrenamt: Funktion); Verwaltung im CRM am Workshop-Datensatz | EVL §3 Vorschlag 22 |
| 23 | **Webinar-Modul direkt verbunden** | `vimeo-webinare` ist von Anfang an angebunden; gleicher Buchungs-Kern. Werkzeugteilung: **Live über Zoho Meetings**, **Vimeo für On-Demand**. Aufzeichnungs-Verteiler: nach Webinar Aufzeichnung nach Vimeo, automatische Mail an alle Buchenden mit Video-Link, Nachhol-Frist (Default 30 Tage), Anti-Skip-Tracking, automatische Bescheinigung bei vollständiger Wiedergabe | EVL §3 Vorschlag 23 |

## 4. Architektur-Kern

### 4.1 Modul-Layout

```
modules/business/workshop-booking/
├── dgptm-workshop-booking.php        Plugin-Header + Bootstrap
├── module.json                       v1.0.0, dependencies: crm-abruf, vimeo-webinare, stipendium, anwesenheitsscanner, zoho-books-integration, mitglieder-dashboard
├── includes/
│   ├── class-entscheidungsvorlage.php       (existiert; Freigabe-Workflow)
│   ├── class-event-source.php               Event-Abruf aus DGfK_Events (Filter Workshop/Webinar, From_Date >= heute)
│   ├── class-booking-service.php            Orchestrator: book($event_id, $attendees) → BookingResult
│   ├── class-stripe-checkout.php            Checkout-Session-Erzeugung, eigenes Unterkonto
│   ├── class-stripe-webhook.php             /wp-json/dgptm-workshop/v1/stripe-webhook
│   ├── class-veranstal-x-contacts.php       Schreib-Layer für Anmelde-Datensätze
│   ├── class-contact-lookup.php             4-Felder-E-Mail-Suche (über crm-abruf)
│   ├── class-ticket-number.php              99999-Präfix-Generator
│   ├── class-ticket-pdf.php                 PDF mit QR-Code (delegiert an certificate-presets)
│   ├── class-qr-generator.php               QR-Code-Erzeugung (Wrapper)
│   ├── class-attendance-bridge.php          Brücke zu anwesenheitsscanner
│   ├── class-attendance-import.php          Live-Tool-Anwesenheitsdaten importieren (Zoho Meetings) + automatische Anwesenheits-Setzung
│   ├── class-recording-distributor.php      Aufzeichnungs-Verteiler (Vimeo)
│   ├── class-certificate-trigger.php        Trigger nach Workshop-Ende + Anwesenheit
│   ├── class-zoho-books-bridge.php          Brücke zu zoho-books-integration (Rechnung in Books, nicht CRM)
│   ├── class-mail-sender.php                wp_mail + ICS-Builder + 7d/1d-Reminder
│   ├── class-waitlist-store.php             FIFO-Warteliste, 24-h-Frist
│   ├── class-token-store.php                Persönliche Links für Nicht-Mitglieder + Designer:innen (Vorbild: stipendium)
│   ├── class-token-installer.php            Tabelle wp_dgptm_workshop_tokens
│   ├── class-cancellation.php               Storno-Logik (AGB §6 Abs. 1) + Stornogebühr-Berechnung
│   ├── class-transfer.php                   Übertragung auf Ersatzteilnehmer:in (AGB §6 Abs. 2)
│   ├── class-postponement.php               Termin-Verlegung (AGB §4) + automatische Mail + Kalender-Update
│   ├── class-sync-coordinator.php           ★ SINGLE ENTRY POINT für CRM-Status-Schreibzugriffe (Veranstal_X_Contacts)
│   ├── class-sync-log-store.php             Append-only Audit-Log (AGB §6 Abs. 3 Schriftform-Backup)
│   ├── class-drift-alert-store.php          Drift-Alerts für Geschäftsstelle (kuratierter Alert-Stream)
│   ├── class-state-machine.php              Erlaubte Blueprint-Übergänge zwischen den 8 Anmelde-Status
│   ├── class-books-status-reader.php        Read-only Books-Anbindung für Drift-Reconciliation
│   ├── class-reconciliation-cron.php        Cron 15 min: Drift-Erkennung Stripe + Books vs. CRM
│   ├── class-i18n.php                       Sprach-Switch DE/EN pro Workshop
│   ├── class-attendance-list-mailer.php     Sammel-Liste mit QR/Ticketnummern an Verantwortliche vor Workshop
│   ├── class-shortcodes.php                 [dgptm_workshops], [dgptm_workshops_success], [dgptm_meine_tickets], [dgptm_designer_layout]
│   └── class-mitgliederbereich-tab.php      Tab „Meine Tickets" in mitglieder-dashboard (Modul- + Backstage-Tickets)
├── templates/
│   ├── entscheidungsvorlage-dokument.php    (existiert)
│   ├── workshop-card.php                    Karten-UI (Vorbild: edugrant/events-list.php)
│   ├── workshop-detail.php                  Detail mit Ticket-Auswahl
│   ├── booking-form.php                     Smart-Form (CRM-Match → Felder ausgeblendet, Daten-Bestätigung)
│   ├── meine-tickets.php                    Vorbild: edugrant/user-grants.php
│   ├── designer-layout-editor.php           Layout-Editor (Token-geschützt)
│   ├── ticket-pdf.php                       Ticket-PDF-Template mit QR
│   ├── certificate-presets/                 Layout-Definitionen (DE/EN)
│   └── mails/                               HTML-Mails (Bestätigung, Warteliste, Nachrücker, Storno, Reminder 7d/1d, Verlegung, Aufzeichnung)
├── assets/                                  CSS + JS (folgt Designsprache aus modules/business/umfragen/assets/css/frontend.css)
└── cron/
    ├── waitlist-watcher.php                 alle 15 min
    ├── reconciliation-cron.php              ★ alle 15 min — Drift-Erkennung Stripe + Books vs. CRM
    ├── pending-bookings-cleanup.php         ★ alle 15 min — abgelaufene Stripe-Sessions aufräumen
    ├── reminder-7d.php                      täglich
    ├── reminder-1d.php                      täglich
    ├── attendance-import.php                stündlich nach Webinar (Live-Tool-Daten)
    └── certificate-trigger.php              täglich (prüft beendete Workshops + Anwesenheit)
```

**Öffentlicher Einstiegspunkt:** `BookingService::get_instance()->book($event_id, $attendees, $invoice_options, $language)` → `BookingResult` mit `checkout_url | confirmation | waitlist_position`.

### 4.2 Bausteine und Verantwortung (laut EVL Abschnitt 4)

| Baustein | Klasse(n) | Wiederverwendung |
|---|---|---|
| Workshops lesen | `class-event-source.php` | nutzt `crm-abruf` |
| Buchung prüfen | `class-booking-service.php` | — |
| Bezahlung (Stripe-Unterkonto) | `class-stripe-checkout.php`, `class-stripe-webhook.php` | — |
| Rechnung (Zoho Books) | `class-zoho-books-bridge.php` | nutzt `zoho-books-integration` (Books-API direkt, **nicht** über CRM) |
| Zoho CRM schreiben | `class-veranstal-x-contacts.php` | nutzt `crm-abruf` |
| E-Mails + Reminder | `class-mail-sender.php` | — |
| Aufzeichnungs-Verteiler | `class-recording-distributor.php` | nutzt `vimeo-webinare/class-vimeo-api.php` |
| Termin-Verlegung | `class-postponement.php` | — |
| Warteliste (FIFO + 24-h-Frist) | `class-waitlist-store.php` | — |
| Frontend (Karten, Formular, Bestätigung) | `class-shortcodes.php`, Templates | UI-Vorbild: `edugrant/templates/events-list.php` |
| Mitgliederbereich-Anzeige | `class-mitgliederbereich-tab.php` | nutzt `mitglieder-dashboard` Tab-System |
| QR-Code-Generator | `class-qr-generator.php` | Wrapper um Bibliothek |
| Ticket-PDF | `class-ticket-pdf.php` | PDF-Lib aus `vimeo-webinare/class-certificate-presets.php` |
| Anwesenheits-Liste an Verantwortliche | `class-attendance-list-mailer.php` | — |
| Ticketprüfung (Webtool) | Erweiterung von `anwesenheitsscanner` (in dessen Codebase) | nutzt `anwesenheitsscanner` direkt |
| Persönliche-Link-Verwaltung | `class-token-store.php`, `class-token-installer.php` | Vorbild + Code-Pattern aus `stipendium/class-gutachter-token.php` |
| Bescheinigungs-Generator | `class-certificate-trigger.php` | nutzt `vimeo-webinare/class-certificate-presets.php` |
| **Status-Sync (Single Entry Point)** | `class-sync-coordinator.php` + `class-sync-log-store.php` + `class-drift-alert-store.php` + `class-state-machine.php` + `class-reconciliation-cron.php` + `class-books-status-reader.php` | siehe Abschnitt 4a |
| Backstage-Spiegelung | **entfällt** — Backstage-Buchungen werden bereits über bestehende Zoho-Flows direkt in `Veranstal_X_Contacts` geschrieben | `Quelle = Backstage` markiert sie; Sync_Coordinator skippt sie konsequent |
| Anwesenheits-Import (Live-Tool) | `class-attendance-import.php` | Zoho-Meetings-API |

## 4a. Status-Sync-Architektur (NEU — bestätigt 30.04.2026)

**Problem:** `Veranstal_X_Contacts` Blueprint-State und Zahlungsstatus müssen jederzeit konsistent mit Stripe und Zoho Books sein. Drift kostet AGB-Konformität (§6 Abs. 3 Schriftform) und vergiftet die Geschäftsstellen-Sicht.

**Zusatz-Komplikation:** Backstage schreibt parallel über Zoho-Flows direkt in `Veranstal_X_Contacts`. Diese Einträge dürfen *nicht* sync't, *nicht* mit Stripe abgeglichen, *nicht* in die Reconciliation einbezogen werden — aber im Mitgliederbereich angezeigt werden.

### 4a.1 Sync-Strategie — Hybrid

```
                        Stripe
                           │
                    [Webhook /v1/stripe-webhook]
                           │
                           ▼
          ┌────────────────────────────────────┐
          │  Sync_Coordinator                  │
          │  1. Schreibt sync_log Eintrag      │
          │  2. Berechnet Ziel-Blueprint-State │
          │  3. Berechnet Ziel-Zahlungsstatus  │
          │  4. Push zum CRM (sofort)          │
          │  5. Bei Fehler: Eintrag bleibt     │
          │     "pending" → Retry vom Cron     │
          └────────────────────────────────────┘
                           │
                           ▼
                ┌──────────────────────┐
                │  Veranstal_X_Contacts│
                │  - Blueprint-State   │
                │  - Zahlungsstatus    │
                │  - Stripe_Charge_ID  │
                │  - Books_Invoice_ID  │
                │  - Last_Sync_At      │
                └──────────────────────┘
                           ▲
                           │
                  ┌────────────────┐
                  │ Reconciliation │  Cron alle 15 min
                  │ Cron           │  COQL: WHERE Quelle = 'Modul'
                  └────────────────┘
                           │
                           ▼
                ┌──────────────────────┐
                │  sync_log (DB)       │  Append-only Audit-Trail
                │  drift_alerts (DB)   │  Kuratierter Alert-Stream
                └──────────────────────┘
```

### 4a.2 Single Entry Point — Sync_Coordinator

Niemand schreibt jemals direkt in `Veranstal_X_Contacts` ohne den Coordinator. `class-veranstal-x-contacts.php` ist private API.

```php
interface Sync_Coordinator_Interface {
    public function apply_intent( Sync_Intent $intent ): Sync_Result;
}

final class Sync_Intent {
    public string  $veranstal_x_contact_id;     // Zoho-ID
    public ?string $target_blueprint_state;     // null = nicht ändern
    public ?string $target_payment_status;      // null = nicht ändern
    public string  $source;                     // stripe_webhook | reconciliation | manual | booking_init
    public array   $payload;                    // stripe_event_id, books_invoice_id, refund_id, …
    public string  $reason;                     // menschlich lesbar
}

final class Sync_Result {
    public bool    $success;
    public ?string $error_code;                 // transition_forbidden | zoho_api_error | drift_detected | source_skipped
    public string  $log_id;                     // FK auf wp_dgptm_workshop_sync_log
    public ?string $alert_id;                   // FK auf drift_alerts (falls erzeugt)
}
```

**Erste Prüfung in `apply_intent()`:**
```
if ( $contact->Quelle !== 'Modul' ) {
    log( source_skipped );
    return Sync_Result::skipped();   // Backstage-Records werden NIE überschrieben
}
```

**Auslöser-Pfade:**

| Auslöser | Sync_Intent |
|---|---|
| Stripe `checkout.session.completed` | `target_blueprint=Angemeldet`, `target_payment=Bezahlt` |
| Stripe `checkout.session.expired` | `target_blueprint=Abgebrochen`, `pending_bookings`-Eintrag löschen |
| Stripe `charge.refunded` | `target_blueprint=Storniert`, `target_payment=Erstattet` |
| `BookingService::book()` | `target_blueprint=Zahlung_ausstehend`, `target_payment=Ausstehend` (`source=booking_init`) |
| Reconciliation-Cron findet Drift | `source=reconciliation` |
| Manueller Storno-AJAX (Phase 6) | `source=manual` |

### 4a.3 State-Machine — erlaubte Blueprint-Übergänge

```
                    [Buchung-Start]
                         │
                         ▼
                  (Zahlung_ausstehend)
                    /         \
        Stripe paid│           │Stripe expired/cancel
                   ▼           ▼
              (Angemeldet)  (Abgebrochen)
                /     \
    User-Storno│       │Stripe refund
        Frist  ▼       ▼
         (Storniert)  (Storniert)

              [Workshop-Ende]
                /         \
   anwesend=ja │           │anwesend=nein
               ▼           ▼
          (Teilgenommen) (Nicht_teilgenommen)


(Warteliste) ──Platz frei──> (Nachrücker_Zahlung_ausstehend)
                                /         \
                    Stripe paid│           │24-h-Frist
                               ▼           ▼
                          (Angemeldet)  (Abgebrochen)
```

`State_Machine::can_transition($from, $to, $source)` lehnt unzulässige Übergänge ab. Ausnahme: `source=manual` mit `manage_options`-Capability darf bestimmte Override-Übergänge (Konfigurierte Liste).

### 4a.4 Drift-Resolution — asymmetrisch

| Drift-Typ | Auflösung |
|---|---|
| Stripe sagt `paid`, CRM sagt `Zahlung ausstehend` | **Auto-Korrektur:** CRM auf `Angemeldet` + Zahlungsstatus `Bezahlt` |
| Stripe sagt `refunded`, CRM sagt `Angemeldet` | **Auto-Korrektur:** CRM auf `Storniert` |
| Stripe sagt `paid`, Books sagt `Rechnung offen` (Zahlungseingang fehlt) | **Auto-Korrektur:** Books-Zahlungseingang verbuchen *(Phase 7; Phase 1 nur Drift-Alert)* |
| **CRM manuell auf `Storniert`, Stripe nicht erstattet** | **KEIN Auto-Push** — Drift-Alert für Geschäftsstelle (manueller Storno = bewusster operativer Akt; ggf. Bar-Erstattung) |
| CRM `Teilgenommen` ohne erfolgten Workshop-Termin | Drift-Alert (Anomalie) |
| CRM `Quelle=Backstage` mit Stripe-Charge im Modul-Konto | Drift-Alert (Migrations-Edge-Case) |

**Rationale für Asymmetrie:** Stripe = Source of Truth für Zahlungsbewegungen (technisch). CRM = Source of Truth für operative Entscheidungen (Geschäftsstelle). Modul-Push ist immer Stripe→CRM, nie CRM→Stripe ohne Bestätigung.

### 4a.5 Backstage-Records (Quelle = Backstage)

Backstage schreibt über bestehende Zoho-Flows weiter direkt in `Veranstal_X_Contacts`. Das Modul behandelt diese Einträge wie folgt:

| Komponente | Verhalten bei `Quelle = Backstage` |
|---|---|
| `Sync_Coordinator` | Skip mit `error_code=source_skipped` |
| `Reconciliation_Cron` | COQL-Filter `WHERE Quelle = 'Modul'` — Backstage wird nie geprüft |
| `Stripe_Webhook` | Wenn Webhook auf Backstage-Record zielt (Edge-Case): Drift-Alert `code=backstage_with_stripe`, kein CRM-Update |
| Mitgliederbereich „Meine Tickets" | **Wird angezeigt** mit Hinweis „Verwaltung über Backstage", read-only, ohne Storno-Aktion |
| `BookingService::book()` | Setzt bei jedem neu angelegten Eintrag `Quelle = Modul` |

### 4a.6 Neue WordPress-Tabellen

**`wp_dgptm_workshop_sync_log`** (append-only Audit-Trail)

| Spalte | Typ | Zweck |
|---|---|---|
| `id` | BIGINT PK AUTO_INCREMENT | — |
| `veranstal_x_contact_id` | VARCHAR(40) NOT NULL, IDX | — |
| `source` | VARCHAR(40) NOT NULL | `stripe_webhook` / `reconciliation` / `manual` / `booking_init` |
| `intent_blueprint_state` | VARCHAR(80) NULL | — |
| `intent_payment_status` | VARCHAR(40) NULL | — |
| `previous_blueprint_state` | VARCHAR(80) NULL | — |
| `previous_payment_status` | VARCHAR(40) NULL | — |
| `success` | TINYINT(1) NOT NULL | — |
| `error_code` | VARCHAR(40) NULL | — |
| `error_message` | TEXT NULL | — |
| `payload_json` | LONGTEXT | stripe_event_id, books_invoice_id, refund_id |
| `reason` | VARCHAR(160) | — |
| `created_at` | DATETIME NOT NULL, IDX | — |

**`wp_dgptm_workshop_drift_alerts`** (kuratierter Alert-Stream für Geschäftsstelle)

| Spalte | Typ |
|---|---|
| `id` | BIGINT PK |
| `veranstal_x_contact_id` | VARCHAR(40), IDX |
| `code` | VARCHAR(60) `manual_storno_without_refund`, `paid_but_status_pending`, `backstage_with_stripe`, … |
| `severity` | ENUM(info, warning, critical) |
| `crm_state_snapshot` | TEXT (JSON) |
| `external_state_snapshot` | TEXT (JSON) |
| `proposed_action` | TEXT NULL |
| `status` | ENUM(open, acknowledged, resolved, ignored) |
| `acknowledged_by` | BIGINT NULL |
| `acknowledged_at` | DATETIME NULL |
| `resolved_at` | DATETIME NULL |
| `created_at` | DATETIME NOT NULL, IDX |

**`wp_dgptm_workshop_pending_bookings`** (Übergangstabelle: Buchung angelegt, Stripe-Session offen)

| Spalte | Typ |
|---|---|
| `id` | BIGINT PK |
| `veranstal_x_contact_id` | VARCHAR(40) UNIQUE |
| `event_id` | VARCHAR(40) NOT NULL |
| `attendees_json` | LONGTEXT |
| `stripe_session_id` | VARCHAR(255) NULL |
| `stripe_session_expires_at` | DATETIME NULL |
| `created_at` | DATETIME NOT NULL |

> Macht das Aufräumen verloren gegangener Sessions deterministisch.

---

## 5. Datenfluss

### 5.1 Standard-Buchung

1. **Workshop entdecken** — `[dgptm_workshops]` zeigt Karten aus DGfK_Events live (kein Cache; Filter `Event_Type IN (Workshop, Webinar)` + `From_Date >= heute`).
2. **Ticket auswählen** — Tickets aus `Tickets`-Array von Zoho Backstage (Vollpreis, Mitgliedspreis, Studi-Tarif, …).
3. **Daten eintragen / Daten prüfen** — Bei Mitgliedern Stammdaten aus CRM vorausgefüllt; Person prüft + bestätigt; geänderte Daten fließen in CRM zurück. Studi-Tarif → Pflicht-Upload Immatrikulationsbescheinigung. Sammel-Buchung → Optional Sammel-Rechnung an Zahler mit Kostenstelle/Auftragsnummer.
4. **Capacity-Check** — Voll → Warteliste (FIFO). Frei → `BookingService::book()` → Sync_Coordinator legt `Veranstal_X_Contacts`-Eintrag an (`Quelle=Modul`, `Anmelde_Status=Zahlung ausstehend`, `Zahlungsstatus=Ausstehend`); paralleler Eintrag in `wp_dgptm_workshop_pending_bookings`.
5. **Stripe Checkout Session** — Eigenes Unterkonto; `allow_promotion_codes: false` in V1; `metadata.veranstal_x_contact_id` setzen für Webhook-Routing. Bei Nicht-Zahlung → `checkout.session.expired` → Sync_Intent `target=Abgebrochen`, `pending_bookings`-Eintrag löschen, Platz frei.
6. **Webhook `checkout.session.completed`** → `Sync_Coordinator.apply_intent(target=Angemeldet, payment=Bezahlt, source=stripe_webhook)`. Phase 1: Status-Update + Bestätigungs-Mail. Phase 2: Ticketnummer (Präfix 99999), QR, Ticket-PDF. Phase 7: Books-Rechnung erzeugen.
7. **Reconciliation-Cron** alle 15 min — fängt verlorene Webhooks ab und gleicht Drift ab (siehe 4a.4).
8. **Mitgliederbereich** — Phase 3: Eingeloggte sehen Ticket unter „Meine Tickets" (sowohl `Quelle=Modul` als auch `Quelle=Backstage`). Gäste/Externe erhalten Token-Link.
9. **Reminder** — Phase 6: Cron 7 Tage und 1 Tag vor Workshop-Start.

### 5.2 Warteliste-Watcher (Cron 15 min)

Prüft Lücken zwischen belegten Plätzen und `Maximum_Attendees`. Lücke → ältester Wartelisten-Eintrag bekommt Mail mit 24-h-Zahlungslink (Status: *Nachrücker:in – Zahlung ausstehend*). Frist verstrichen → Status *Abgebrochen*, nächster Wartelisten-Eintrag.

### 5.3 Backstage-Buchungen (über bestehende Zoho-Flows)

**Keine separate Spiegelung** — Backstage schreibt über bestehende Zoho-Flows direkt in `Veranstal_X_Contacts`. Diese Einträge tragen `Quelle ≠ 'Modul'` (typischerweise `Backstage` oder leer) und werden vom Modul wie folgt behandelt:

- `Sync_Coordinator` skippt sie konsequent
- `Reconciliation_Cron` filtert per COQL `WHERE Quelle = 'Modul'`
- Mitgliederbereich zeigt sie an (read-only, Hinweis „Verwaltung über Backstage")

Siehe Abschnitt 4a.5 für Details.

### 5.4 Storno-Pfade (AGB §6)

**Pfad A (§6 Abs. 1) — Stornierung bis Frist:**
- Default-Frist 42 Tage (6 Wochen) vor Workshop-Beginn; pro Workshop einstellbar (28–42 Tage) am CRM-Feld `Storno_Frist_Tage`.
- Erstattung automatisch über Stripe abzüglich Stornogebühr **min(10 % Ticketpreis, 35 €) pro Ticket**; Gebühr als separater Posten in Zoho Books.
- Status → *Storniert*.
- Nach Frist → kein Self-Service; Status *Angemeldet, nicht teilgenommen* nach Workshop-Ende; volle Gebühr fällig (AGB-konform); Härtefall nur über Geschäftsstelle.

**Pfad B (§6 Abs. 2) — Übertragung auf Ersatzteilnehmer:in:**
- Bis Ablauf des **drittletzten Werktags** vor Veranstaltungsbeginn.
- Ersatzteilnehmer:in legt eigene Buchung an; Original-Ticket wird storniert.
- Bearbeitungsgebühr **min(20 % Ticketpreis, 70 €)**.
- Bei Workshops mit Warteliste: freiwerdender Platz **vorrangig** an Wartelisten-Eintrag; danach Übertragung.

### 5.5 Termin-Verlegung (AGB §4)

`class-postponement.php` reagiert auf Datum-Änderung am DGfK_Events-Datensatz im CRM:
1. Update aller `Veranstal_X_Contacts`-Einträge.
2. Mail an alle Teilnehmer:innen + Wartelisten-Einträge mit neuem Termin + neuem ICS.
3. Standard-Storno-Bedingungen (§6) bleiben anwendbar; Sonderkulanz im Einzelfall über Geschäftsstelle.
4. Bei Absage durch Veranstalter (§4 Abs. 3): volle Erstattung über Stripe.
5. Bei höherer Gewalt (§10): Veranstalter von Erstattungs-Pflichten befreit (manuelle Entscheidung).

### 5.6 Anwesenheit + Bescheinigung

**Präsenz-Workshop:**
- Einlass-Scan via `anwesenheitsscanner` (Mobile-Browser) → setzt Anwesenheits-Marker im CRM.
- Optional Ausgangs-Scan; ≤ 10 % Frühausstieg unkritisch.
- Verantwortliche Person (CRM-Feld) erhält vor Workshop **automatische Sammel-Liste mit allen QR-Codes/Ticketnummern** als Backup (`class-attendance-list-mailer.php`).
- Manuelle Nachpflege jederzeit möglich.

**Online-Webinar (Live, auch 100+ TN):**
- Anwesenheitsdauer aus Live-Tool (Zoho Meetings: Anwesenheits-Export pro TN mit Beitritts-/Verlassenszeit) — automatisch via `class-attendance-import.php` über E-Mail abgeglichen.
- Errechnete Anwesenheitsdauer → Vergleich mit `Anwesenheits_Schwelle` (CRM-Feld am Workshop, frei konfigurierbar 0–100 %).
- ≥ Schwelle → Status *Teilgenommen*; sonst *Nicht teilgenommen*.
- Manuelle Korrektur durch Kursleitung im CRM jederzeit (Edge Cases, technische Probleme).

**Online On-Demand-Nachholung:**
- Aufzeichnung wird nach Vimeo hochgeladen.
- `class-recording-distributor.php` mailt Video-Link an alle Buchenden (Live-TN zum Nachschauen, Nicht-Live-TN zur Nachholung).
- Nachhol-Frist Default **30 Tage** nach Webinar (pro Webinar einstellbar; bei FoBi/VNR ggf. enger).
- Anti-Skip-Tracking aus `vimeo-webinare` → vollständige Wiedergabe Voraussetzung.
- Vollständige Wiedergabe → automatische Bescheinigung (gleicher Workflow).

**Bescheinigungs-Trigger** (`class-certificate-trigger.php`, Cron täglich):
1. Workshop-Ende erreicht?
2. Status = *Teilgenommen*?
3. Ja → PDF erzeugen via `vimeo-webinare/class-certificate-presets.php` mit Workshop-Layout (Standard oder eigenes); E-Mail-Versand; Mitgliederbereich-Freigabe.
4. **Mehrtägige Veranstaltungen:** **Eine Sammelbescheinigung** nach Veranstaltungsende mit Tagesübersicht (anwesende Tage, errechnete FoBi-Punkte) — keine Tagesbescheinigungen.

## 6. CRM-Erweiterungen (`DGfK_Events` und `Veranstal_X_Contacts`)

### 6.1 Neue Felder am Workshop-Datensatz (`DGfK_Events`)

| Feld | Typ | Default | Zweck |
|---|---|---|---|
| `Storno_Frist_Tage` | Zahl | 42 | Self-Service-Storno-Frist (28–42 d empfohlen) |
| `Anwesenheits_Schwelle_Prozent` | Zahl | 80 | Mindest-Anwesenheit für „anwesend" bei Online-Live |
| `EduGrant_Verfuegbar` | Bool | false | Anzeige des EduGrant-Hinweises auf Karte |
| `EduGrant_Hoehe_EUR` | Zahl | — | Förderhöhe pro Platz |
| `EduGrant_Plaetze_Gesamt` | Zahl | — | Förderplatz-Kontingent (z. B. 3 von 20) |
| `EduGrant_Plaetze_Vergeben` | Zahl | 0 | Genutzte Förderplätze; bei == Gesamt → Hinweis ausgeblendet |
| `Verantwortliche_Person` | Lookup → Contacts | — | Empfänger:in der Anwesenheits-Liste |
| `Ticket_Layout` | Lookup → Layout-Tabelle | — | Optional; leer = Standard-Layout |
| `Sprache` | Picklist (DE/EN) | DE | Pro Workshop; steuert Frontend, Mails, Bescheinigung |

### 6.2 Pflichtfelder am Buchungs-Datensatz (`Veranstal_X_Contacts`) — durch Modul gesetzt/genutzt

| Feld | Typ | Pflicht | Zweck |
|---|---|---|---|
| `Quelle` | Picklist (`Modul`, `Backstage`) | **ja** | Sync_Coordinator skippt alles ≠ `Modul`; Backstage-Flow darf weiter unverändert schreiben (Default: leer oder `Backstage`) |
| `Anmelde_Status` | Picklist (8 Werte, siehe 6.3) | ja | Blueprint-State |
| `Zahlungsstatus` | Picklist (`Ausstehend`, `Bezahlt`, `Erstattet`, `Teilerstattet`) | ja | gespiegelt aus Stripe |
| `Stripe_Charge_ID` | Text | nein | Referenz zur Stripe-Charge |
| `Stripe_Session_ID` | Text | nein | Referenz zur Checkout-Session |
| `Books_Invoice_ID` | Text | nein | Referenz zur Books-Rechnung (Phase 7) |
| `Last_Sync_At` | DateTime | nein | letzter erfolgreicher Sync_Coordinator-Push |

> **Achtung CRM-Setup:** Wenn `Quelle` heute noch nicht existiert, muss es vor V1-Go-Live angelegt werden. Bestehende Backstage-Flow-Einträge sollten per einmaligem Bulk-Update auf `Quelle = Backstage` gesetzt werden, damit das Modul sie sicher überspringt.

### 6.3 Konsolidierte Anmelde-Status (`Veranstal_X_Contacts`)

8 Werte, abgestimmt 30.04.2026:

| Status | Bedeutung |
|---|---|
| Zahlung ausstehend | Person ist auf Stripe-Bezahlseite |
| Angemeldet | Zahlung erfolgt, Ticket gültig, Workshop noch nicht stattgefunden |
| Warteliste | Workshop voll, FIFO-Position |
| Nachrücker:in – Zahlung ausstehend | 24-h-Frist läuft |
| Abgebrochen | Nachrück-Frist verstrichen, Platz neu vergeben |
| Storniert | Geld (abzüglich Stornogebühr) erstattet |
| Teilgenommen | Anwesenheit bestätigt, Bescheinigung erstellt |
| Nicht teilgenommen | Workshop vorbei, Person war nicht da; keine Bescheinigung, keine FoBi-Punkte |

### 6.4 WordPress-seitige Tabellen

| Tabelle | Phase | Zweck | Vorbild |
|---|---|---|---|
| `wp_dgptm_workshop_sync_log` | **1** | Append-only Audit-Trail aller Sync-Operationen (AGB §6 Abs. 3 Schriftform-Backup) | siehe Abschnitt 4a.6 |
| `wp_dgptm_workshop_drift_alerts` | **1** | Kuratierter Alert-Stream für Geschäftsstelle | siehe 4a.6 |
| `wp_dgptm_workshop_pending_bookings` | **1** | Übergangstabelle: Buchung angelegt, Stripe-Session noch offen | siehe 4a.6 |
| `wp_dgptm_workshop_tokens` | 2 | Persönliche Links für Nicht-Mitglieder + Designer:innen | `stipendium/class-token-installer.php` |
| `wp_dgptm_workshop_layouts` | 4 | Hinterlegte Bescheinigungs-Layouts pro Workshop | — |
| `wp_dgptm_workshop_attendance_log` | 4 | Audit-Log für Anwesenheits-Änderungen | — |

## 7. AGB-Abgleich (Stand: Februar 2024)

| AGB-Punkt | Umsetzung im Modul |
|---|---|
| §1 Anmeldung & Vertragsschluss | Buchungsformular + Bestätigungsmail nach erfolgreicher Zahlung |
| §1 Abs. 3 Einzelanmeldung pro Person | Pro TN ein `Veranstal_X_Contacts`-Eintrag; eine zahlende Person kann mehrere Tickets in einer Bestellung bündeln |
| §2 Rechnung mit Anmeldebestätigung | Rechnung direkt in Zoho Books; Anhang an Bestätigungsmail |
| §3 Fortbildungspunkte | EIV-Fobi-Anschluss + EFN aus CRM; Punkte-Anzeige auf Workshop-Karte |
| §4 Verlegung durch Veranstalter | `class-postponement.php` mit automatischer Mail + Kalender-Update |
| §6 Abs. 1 Stornierung 6 Wochen / 10 % / max. 35 € | `class-cancellation.php` mit Stripe-Erstattung |
| §6 Abs. 2 Übertragung 3 Werktage / 20 % / max. 70 € | `class-transfer.php` mit Wartelisten-Vorrang |
| §6 Abs. 3 Schriftform | Online-Aktion erzeugt automatisch schriftliche Mail-Bestätigung; Audit-Log |
| §7 Datenschutz + EFN-Einwilligung | Pflicht-Häkchen im Buchungsformular; Speicherung am CRM |
| §10 Höhere Gewalt | Manueller Entscheidungs-Pfad in Verlegungs-Workflow |

## 8. Externe Abhängigkeiten

| Dienst | Wofür | Stand |
|---|---|---|
| **Stripe** (eigenes Unterkonto Workshops/Webinare) | Bezahlung + automatische Erstattung; Karte/SEPA/Apple Pay/PayPal | Konto vorhanden, Unterkonto + Webhook-Secret zu konfigurieren |
| **Zoho CRM** | DGfK_Events lesen, Veranstal_X_Contacts schreiben, Contacts anlegen/aktualisieren | aktiv, Schreibrechte vorhanden |
| **Zoho Books** | Rechnungs-Erstellung **direkt** (nicht über CRM); Vorlage frei definierbar; Nummernkreis aus Books | aktiv, anzubinden über `zoho-books-integration` |
| **Zoho Meetings** | Live-Webinare; Anwesenheits-Export für `class-attendance-import.php` | aktiv |
| **Vimeo** | On-Demand-Aufzeichnungen + Anti-Skip-Tracking | aktiv (`vimeo-webinare`) |
| **EIV-Fobi** | EFN-Punkte-Meldung nach Workshop-Abschluss; EFN aus CRM | **direkt mit angeschlossen in V1** (Änderung gegenüber Erst-Spec, wo dies V2 war) |

## 9. Nicht im Scope (V1)

- Kongresse und Sachkundekurse (bleiben in Backstage; Tickets werden lediglich gespiegelt)
- Native App für Anwesenheits-Erfassung (V1: Web-Tool im mobilen Browser)
- Aktive Rabattcodes (Logik vorbereitet)
- EFN-Erfassung im Buchungsformular für Nicht-Mitglieder/Ärzt:innen (für Mitglieder: aus CRM)
- Massenmigration historischer Backstage-Buchungen (nur künftige + aktive)
- Conditional-Field-Logik darüber hinaus, was am Ticket-Typ konfigurierbar ist (z. B. dynamische Pflichtfelder mit Abhängigkeiten)

## 10. Offene Punkte (alle 18 EVL-Fragen)

15 von 18 Fragen sind in den Review-Runden geklärt. Verbleibende Klärungspunkte vor Implementierungs-Start:

| # | Frage | Stand | Verantwortung |
|---|---|---|---|
| 1–9 | Storno-Frist, Refund nach Frist, Status-Wording, EduGrant-Anzeige, Backstage-Sync, Backstage-Migration, Ticketnummern-Format, Token-Gültigkeit, Designer-Einladung | **geklärt 30.04.2026** (siehe oben) | — |
| 10 | Standard-Layout Bescheinigung | **geklärt:** wird im Implementierungs-Verlauf mit Geschäftsstelle entwickelt | Geschäftsstelle + Designer |
| 11 | Anwesenheits-Erfassung | **geklärt 30.04.2026:** differenziert nach Format (siehe §5.6) | — |
| 12 | App vs. Web | **geklärt 30.04.2026:** V1 = Web-Tool | — |
| 13 | Sammel-Rechnung | **geklärt 30.04.2026:** in V1 nachziehen, optional an Zahler | — |
| 14 | Books-Workflow | **geklärt 30.04.2026:** Vorlage frei in Books, Nummernkreis aus Books, EduGrant unabhängig (volle Rechnung an TN, Förderung intern als separate Buchung) | — |
| 15 | Mehrtägige Bescheinigung | **geklärt 30.04.2026:** eine Sammelbescheinigung mit Tagesübersicht | — |
| 16 | Verlegungs-Stornorecht | **geklärt 30.04.2026:** AGB §4 + §6 maßgeblich; Sonderkulanz Einzelfall | — |
| 17 | Studi-Nachweis | **geklärt 30.04.2026:** Pflicht-Upload, Geschäftsstellen-Prüfung, Differenz-Nachforderung bei Ablehnung | — |
| 18 | Anwesenheits-Schwelle | **geklärt 30.04.2026:** frei pro Workshop konfigurierbar | — |

**Wirklich noch offen:**
- **Standard-Layout der Teilnahmebescheinigung** — wird im Implementierungs-Verlauf entwickelt; Designer:innen-Einladung über Token-Link. Kein Blocker für Implementierungs-Start, aber V1-Release-Voraussetzung.
- **Stripe-Unterkonto** — buchhalterische Trennung, Konto-Setup vor Webhook-Konfiguration nötig.

## 11. Implementierungs-Phasen (Schätzung, gegenüber 2026-04-22 erweitert)

| Phase | Aufwand alt (22.04.) | Aufwand neu (30.04.) |
|---|---|---|
| Phase 1 — Core (Events, Booking, Stripe, CRM, Mail, Wiederverwendung Helpers) | 3 PT | **4 PT** |
| Phase 2 — Tickets (PDF, QR, Ticketnummer, Auto-Zuordnung, Token für Externe) | — | **2,5 PT** |
| Phase 3 — Mitgliederbereich „Meine Tickets" (Modul + Backstage-Records, read-only-Hinweis bei Backstage) | — | **1,5 PT** |
| Phase 4 — Bescheinigungen (Engine-Anbindung, Trigger, Sammelbescheinigung, Layout-Editor für Designer) | — | **3 PT** |
| Phase 5 — Webinare (vimeo-webinare-Integration, Aufzeichnungs-Verteiler, Anti-Skip, Anwesenheits-Import Zoho Meetings) | — | **2,5 PT** |
| Phase 6 — Storno + Übertragung + Termin-Verlegung (AGB §4, §6 Abs. 1+2) | — | **2 PT** |
| Phase 7 — Books-Anbindung + Sammel-Rechnung | — | **1,5 PT** |
| Phase 8 — Mehrsprachigkeit DE/EN | — | **1,5 PT** |
| Phase 9 — Anwesenheitsscanner-Erweiterung um Workshop-Tickets | — | **1 PT** |
| Frontend (Shortcodes, Templates, Designsprache aus Umfragen-Modul, Smart-Form) | 1,5 PT | **2 PT** |
| Warteliste, Reminder 7d/1d, Webhook-Edge-Cases | 1,5 PT | **1,5 PT** |
| Test auf Staging + Anpassungen | 1 PT | **2 PT** |
| **Summe** | **~7 PT** | **~25,5 PT (≈ 5 Wochen bei voller Auslastung)** |

Begründung der Erweiterung: Tickets, QR, Mitgliederbereich, Bescheinigungen, Webinar-Integration, Termin-Verlegung, Books, Mehrsprachigkeit waren in der Erst-Spec nicht enthalten — sie sind in den Review-Runden 2–5 dazugekommen.

## 12. Kompatibilität & Migration

- **Edugrant-Modul** — Modul prüft Edugrant-Felder am DGfK_Events-Datensatz; bei `EduGrant_Verfuegbar = true` und freien Plätzen erscheint Hinweis + Link zum Förderantrag. Kontingent-Logik im Modul (Hinweis verschwindet bei `Plaetze_Vergeben == Plaetze_Gesamt`). Keine Funktions-Duplizierung.
- **Webinar-CRM-Sync** (Spec v. 15.04.26) — `class-veranstal-x-contacts.php` und `class-contact-lookup.php` werden so gestaltet, dass diese Spec sie referenzieren kann. Nach Verschiebung in ein Shared-Modul (`modules/core-infrastructure/event-bookings-shared`?) ist Wiederverwendung trivial — geplant für V1.1.
- **vimeo-webinare** — Wird nicht migriert, sondern direkt eingebunden. Bestehende Webinar-Player-Logik bleibt; `workshop-booking` ergänzt Buchung, Tickets, Bescheinigungs-Trigger, Aufzeichnungs-Verteiler, Anwesenheits-Import.
- **fortbildung** Post-Type — Bei Status *Teilgenommen* legt das Modul automatisch `fortbildung`-Post an (analog vimeo-webinare-Workflow); Bescheinigungs-PDF wird angehängt. EFN aus CRM, EIV-Fobi-Übermittlung in V1.
- **anwesenheitsscanner** — Wird um Workshop-Tickets-Modus erweitert (nicht neu gebaut); akzeptiert sowohl Backstage- als auch Modul-Ticketnummern (Präfix-Erkennung).

## 13. Frontend-Designsprache

Folgt CLAUDE.md des DGPTMSuite-Repos: Tokens und Komponenten aus `modules/business/umfragen/assets/css/frontend.css` (Primär `#4f46e5`, Radius 12px/8px, etc.). **Keine** Elementor-/Astra-Vererbung, auch wenn Buchungsseiten mit Elementor gebaut sind. Klassen-Präfix `dgptm-wsb-*`. E-Mail-Templates nutzen weiterhin DGPTM-Header `#003366`.

## 14. Risiken und Annahmen

| Risiko / Annahme | Mitigation |
|---|---|
| Stripe-Unterkonto-Setup verzögert sich | Module-Code an `account_id`-Parameter koppeln; bis Setup `null` (= Hauptkonto), danach umstellbar |
| Backstage-API-Stabilität für Spiegelung (alle 15 min) | Cron mit Backoff + Logging; bei Fehler letzten erfolgreichen Stand behalten |
| Zoho-Meetings-Anwesenheits-API liefert nicht für jeden Live-Tool-Use-Case Daten | Manuelle Nachpflege durch Kursleitung im CRM bleibt verbindlicher Fallback |
| Designer:innen-Layout-Editor: Browser-Kompatibilität bei komplexen Hintergrundbildern | Server-side PDF-Vorschau via `class-certificate-presets.php`, kein Browser-Rendering der finalen PDF |
| AGB-Konformität bei Stornogebühr/Übertragung | Audit-Log für jede Storno-/Übertragungs-Aktion (`wp_dgptm_workshop_attendance_log`-Pendant) |
| EFN-Übermittlung an EIV-Fobi (V1 dabei statt V2) | Wenn API-Anbindung scheitert: Fallback auf manuelle Erfassung; `fortbildung`-Post bleibt erhalten |

## 15. Verbindung zur Entscheidungsvorlage

Das EVL-Dokument (`templates/entscheidungsvorlage-dokument.php` + zugehörige `class-entscheidungsvorlage.php`) ist die **Quelle der Wahrheit für Inhalte und Begründungen**. Diese Spec referenziert pro Entscheidung den EVL-Punkt. Bei späteren Änderungen am EVL-Dokument ist diese Spec entsprechend nachzuziehen — Spec-Änderungen ohne EVL-Update gelten als nicht freigegeben.

**Freigabe-Workflow (`DGPTM_Workshop_Entscheidungsvorlage`):**
- Mitglieder lesen das Dokument, hinterlassen pro Abschnitt oder pro Vorschlags-Zeile Kommentare, klicken „Vorschlag mittragen" oder „Entscheidungsvorlage freigeben".
- Admin (Geschäftsstelle/Vorstand): markiert Kommentare als *eingearbeitet*; sendet „Beteiligte benachrichtigen"-Mail nach jeder Review-Runde.
- Implementierungs-Start setzt Vorstands-Freigabe der Vorlage als Ganzes voraus.

---

## Änderungsprotokoll dieser Spec

- **22.04.2026** — Erstfassung: Workshop-Buchung als kleines Modul, ~7 PT, 12 Vorschläge, 6 offene Punkte.
- **30.04.2026 (vormittags)** — Komplettrevision nach 5. Review-Runde:
  - Scope erweitert: Workshops + Webinare (statt nur Workshops)
  - 23 Vorschläge (statt 12); 18 offene Punkte (15 davon geklärt)
  - Tickets, QR-Code, Mitgliederbereich, Teilnahmebescheinigungen integriert
  - AGB-Abgleich (§1, §2, §3, §4, §6 Abs. 1+2, §6 Abs. 3, §7, §10) ergänzt
  - Stripe-Unterkonto (statt Hauptkonto); PayPal über Stripe; Sammel-Rechnung
  - Stornogebühr 10 % / max. 35 € + Übertragung 20 % / max. 70 € (AGB §6 Abs. 2)
  - Zoho Books direkt (nicht über CRM); EduGrant unabhängig von Rechnung
  - Termin-Verlegung-Workflow (AGB §4); Aufzeichnungs-Verteiler für Webinare
  - Anwesenheit: Live-Tool-Daten + QR-Scan + Anti-Skip; Schwelle pro Workshop
  - Mehrsprachigkeit DE/EN
  - Wiederverwendung explizit dokumentiert: `vimeo-webinare`, `stipendium`, `anwesenheitsscanner`, `zoho-books-integration`, `edugrant`, `mitglieder-dashboard`, `crm-abruf`
  - EIV-Fobi-Anschluss in V1 (statt V2)
  - Aufwand neu geschätzt: ~25,5 PT (≈ 5 Wochen)
- **30.04.2026 (nachmittags)** — Status-Sync-Architektur ergänzt vor Phase-1-Implementierung:
  - Neuer Abschnitt **4a — Status-Sync-Architektur**: Hybrid (Webhook + Reconciliation-Cron alle 15 min)
  - `Sync_Coordinator` als **Single Entry Point** für alle CRM-Status-Schreibzugriffe
  - State-Machine mit erlaubten Blueprint-Übergängen (siehe 4a.3)
  - Asymmetrische Drift-Resolution-Regeln (siehe 4a.4)
  - **Backstage-Klarstellung:** `class-backstage-mirror.php` entfällt; Backstage-Flows schreiben weiter direkt in `Veranstal_X_Contacts`; `Sync_Coordinator` skippt `Quelle ≠ 'Modul'`-Einträge konsequent
  - Mitgliederbereich zeigt Backstage-Tickets read-only mit Hinweis „Verwaltung über Backstage"
  - Drei neue WP-Tabellen für Phase 1: `wp_dgptm_workshop_sync_log`, `wp_dgptm_workshop_drift_alerts`, `wp_dgptm_workshop_pending_bookings`
  - Pflichtfelder am `Veranstal_X_Contacts`: `Quelle`, `Anmelde_Status`, `Zahlungsstatus`, `Stripe_Charge_ID`, `Stripe_Session_ID`, `Books_Invoice_ID`, `Last_Sync_At`
- **02.05.2026** — Phase 2 implementiert (`feat/workshop-booking-phase-1` aufbauend, neuer Branch `feat/workshop-booking-phase-2`):
  - Ticketnummer-Generator (`class-ticket-number.php`): Präfix `99999` + laufende Nummer; eindeutige Unterscheidung von Backstage-Tickets bei kompatiblem Format
  - QR-Code (`class-qr-generator.php`): Wrapper um `endroid/qr-code` (Composer); graceful Fallback wenn Library fehlt
  - Ticket-PDF (`class-ticket-pdf.php`): A4 mit DGPTM-Header (`#003366`), QR-Code rechts, Ticketnummer prominent; via `dompdf/dompdf` (Composer)
  - Token-Layer für Externe (`class-token-installer.php`, `class-token-store.php`): Tabelle `wp_dgptm_workshop_tokens` mit Scope `booking` (Nicht-Mitglieder, gültig bis Workshop-Ende + 30 Tage) und `layout` (Designer:innen, 14 Tage). Pattern aus `stipendium/class-gutachter-token.php`.
  - Stripe-Webhook erweitert: nach `checkout.session.completed` zweiter Sync_Intent für Ticketnummer-Schreibung; Mail mit Ticket-PDF im Anhang
  - Booking_Service erweitert: Freitickets bekommen sofort Ticketnummer + Mail mit PDF
  - Mail_Sender erweitert: Ticket-PDF und Token-Link für Nicht-WP-User automatisch in Bestätigungs-Mail
  - Neuer Shortcode `[dgptm_workshop_ticket]`: Token-Login für Externe (URL `/veranstaltungen/ticket/?dgptm_wsb_token=…`); inline PDF-Download über `?dgptm_wsb_pdf=…`
  - composer.json mit `dompdf/dompdf:^2.0` + `endroid/qr-code:^4.8` (parallel zu `vimeo-webinare`)
  - **Phase-2-Bibliotheken sind Composer-only**: `vendor/` ist gitignored, vor Deploy `composer install` im Modulordner ausführen

*Dokument Stand: 02.05.2026, Sebastian Melzer — DGPTM IT-Verantwortung.*
