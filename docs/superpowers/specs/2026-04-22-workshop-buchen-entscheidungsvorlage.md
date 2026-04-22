# Workshop buchen — Entscheidungsvorlage

**Datum:** 22.04.2026
**Status:** Design-Zwischenstand zur Abstimmung
**Empfänger:in:** Vorstand, Geschäftsstelle, Kursleitungen
**Zweck:** Freigabe der Design-Eckpunkte vor Implementierung

---

## 1. Ziel des Moduls

Neues DGPTMSuite-Modul **`workshop-booking`**, das zukünftige Veranstaltungen (Workshops) aus dem Zoho CRM (`DGfK_Events`) liest und online buchbar macht. Die Buchung läuft entweder kostenlos (Freiticket) oder über Stripe-Zahlung. Jede Buchung erzeugt einen Eintrag im CRM-Modul `Veranstal_X_Contacts` mit Status *Nicht abgerechnet* und Blueprint *Angemeldet*. Das Modul ist so gebaut, dass spätere Module (Webinar-Buchung, Kongress-Buchung) die Kernkomponenten wiederverwenden können.

## 2. Ausgangslage

| Element | Stand |
|---|---|
| **Edugrant-Modul** | Liest bereits Events aus `DGfK_Events`, zeigt Karten-UI. Dient als Blaupause für das Event-Listing. |
| **vimeo-webinare** (v2.0.1) | Live, eigene Webinar-Logik mit PDF-Zertifikat. Existierende Ticket-/Buchungs-Logik: keine. |
| **Webinar-CRM-Sync** (Spec v. 15.04.26) | Plant bidirektionale Sync zwischen Zoho Meeting und CRM. **Billing ausdrücklich out of scope** — genau diese Lücke füllt das neue Modul. |
| **stripe-formidable** | Bestehende Stripe-Integration, jedoch Formidable-Forms-gekoppelt. Wird nicht wiederverwendet. |

## 3. Getroffene Design-Entscheidungen

| # | Entscheidungspunkt | Gewählt | Begründung |
|---|---|---|---|
| 1 | **Modul-Scope** | Workshop-Modul jetzt, Komponenten für spätere Verallgemeinerung vorbereitet | Überschaubarer Scope, zukunftssicher ohne Over-Engineering |
| 2 | **User-Authentifizierung** | Hybrid: Eingeloggte one-click, Gäste per Formular | Maximale Reichweite; Vor-/Nachname + E-Mail reichen als Einstieg |
| 3 | **Bezahl-Integration** | Stripe Checkout Session (hosted); bei Nicht-Zahlung wird Buchung wieder gelöscht | Weniger Code, volle PCI-Compliance durch Stripe, SEPA/Apple Pay umsonst |
| 4 | **Tickets pro Buchung** | Mehrere Tickets mit Teilnehmer:innen-Daten; pro Person ein Veranstal_X_Contacts-Eintrag | Korrekte Zertifikats- und Fortbildungspunkte-Vergabe pro Person |
| 5 | **Erfasste Daten pro Ticket** | Minimal (Vor-/Nachname, E-Mail) + Adresse optional; Smart-Form blendet Felder aus, wenn CRM-Match | Datensparsam, benutzerfreundlich für Bestands-Mitglieder |
| 6 | **Kontakt-Matching** | 4-Felder-E-Mail-Suche (`Email`, `Secondary_Email`, `Third_Email`, `Fourth_Email` via COQL-Fallback). Kein Treffer → Contact-Neuanlage | Kompatibel mit vorhandener Webinar-Sync-Logik, keine Dubletten |
| 7 | **UI-Integration** | Shortcodes: `[dgptm_workshops]` (Liste/Detail/Formular) und `[dgptm_workshops_success]` (Bestätigung) | Freie Gestaltung der WP-Seite durch Geschäftsstelle; etabliertes DGPTMSuite-Muster |
| 8 | **Kapazität** | Hartes Limit pro Event + **automatische Warteliste** mit 24-h-Nachrück-Frist | Faire FIFO-Logik, keine Enttäuschung durch dauerhafte Sperre |
| 9 | **Storno** | Hybrid: User kann bis zur Frist selbst stornieren (Stripe-Refund automatisch); danach nur Geschäftsstelle, kein/teilweiser Refund | Entlastet Geschäftsstelle, AGB-konform |
| 10 | **E-Mails** | Hybrid: Transactional (Bestätigung, Warteliste, Nachrücker, Storno) über `wp_mail` aus dem Modul; Info-/Erinnerungs-Mails über Zoho CRM / Marketing Automation | Zeitkritische Mails sofort, ICS-Anhang möglich; Marketing-Content flexibel |
| 11 | **Promo-Codes** | Stripe-nativ: Option `allow_promotion_codes: true` in der Checkout-Session. `PromoCodesCSV` aus CRM wird optional zu Stripe-Coupons gespiegelt | Keine eigene Promo-UI nötig |
| 12 | **Architektur-Ansatz** | Eigenständiges Modul mit Service-Interfaces (`EventSource`, `PaymentGateway`, `BookingWriter`, `MailSender`, `WaitlistStore`) | Spätere Extraktion in Shared-Modul per Namespace-Umzug trivial |

## 4. Architektur-Kern

```
modules/business/workshop-booking/
├── src/
│   ├── Contracts/           Interfaces
│   ├── Events/              CRM-Event-Abruf + Ticket-Parsing
│   ├── Booking/             Orchestrator + Value Objects
│   ├── Payment/             Stripe-Checkout + Webhook
│   ├── Crm/                 Veranstal_X_Contacts + 4-Felder-Lookup
│   ├── Mail/                wp_mail + ICS-Builder
│   ├── Waitlist/            Nachrück-Logik, Cron-Watcher
│   ├── Ajax/                Contact-Lookup + Booking-Submit
│   └── Shortcodes/          [dgptm_workshops], [dgptm_workshops_success]
├── templates/               Frontend + E-Mail-Templates
├── assets/                  CSS + JS
└── cron/                    Warteliste-Watcher (15 min)
```

**Öffentlicher Einstiegspunkt:** `BookingService::get_instance()->book($event_id, $attendees)` gibt ein `BookingResult` mit entweder `checkout_url`, `confirmation` oder `waitlist_position`.

## 5. Datenfluss (Überblick)

1. **Event-Anzeige** — Cron-unabhängig: Live-Abruf aus CRM über bestehendes `crm-abruf`-Modul, Filter `Event_Type = "Workshop"` + `From_Date >= heute`.
2. **Ticket-Auswahl** — Tickets kommen aus dem Event-Record (`Tickets`-Array von Zoho Backstage).
3. **Buchungs-Submit** — Capacity-Check → Veranstal_X_Contacts-Eintrag mit Status *Zahlung ausstehend* (oder *Warteliste*) → bei bezahlten Tickets: Stripe Checkout Session erzeugen.
4. **Stripe-Webhook** —
   - `checkout.session.completed` → Status auf *Nicht abgerechnet*, Blueprint auf *Angemeldet*, Bestätigungs-Mail mit ICS.
   - `checkout.session.expired` → Veranstal_X_Contacts-Eintrag wird gelöscht, Platz frei.
5. **Warteliste-Watcher** (15 min) — Prüft Lücken zwischen belegten Plätzen und `Maximum_Attendees`; bei Lücke: ältester Wartelisten-Eintrag wird per E-Mail mit 24-h-Zahlungslink benachrichtigt.

## 6. Kompatibilität zu bestehenden Modulen

- **Edugrant** — Workshop-Buchungsmodul prüft, ob Event eine Edugrant-Förderung hat (`Maximum_Promotion` gesetzt). Falls ja: Hinweis auf Karte („Für diese Veranstaltung ist EduGrant möglich") + Link zum Edugrant-Antrag. Keine Funktions-Duplizierung.
- **Webinar-CRM-Sync** — Shared `Crm\VeranstalXContactsWriter` und `ContactLookup` werden so gestaltet, dass die Webinar-Sync-Spec sie später referenzieren kann. Die 4-Felder-Mail-Logik wird einmalig implementiert und beiden Modulen zur Verfügung gestellt (nach Verschiebung in Shared-Modul).
- **vimeo-webinare** — Kein direkter Konflikt; spätere Verallgemeinerung des Moduls auf Webinar-Buchung ist vorbereitet.

## 7. Neue Felder / Erweiterungen im CRM

- **DGfK_Events** — Vorschlag für neues Feld `Storno_Frist_Tage` (Zahl, Standard 14). Steuert Self-Service-Storno-Frist. *→ Abstimmung mit Geschäftsstelle nötig.*
- **Veranstal_X_Contacts** — Neue Status-Werte ggf. erforderlich:
  - *Zahlung ausstehend* (während Stripe-Session offen)
  - *Warteliste* (Capacity-überlauf)
  - *Nachrücker – Zahlung ausstehend* (24-h-Frist aktiv)
  - *Storniert* (Refund erfolgt)
  *→ Abstimmung mit Blueprint-Verantwortlichen nötig.*

## 8. Externe Abhängigkeiten

- **Stripe-Konto** — Aktiv. Webhook-Secret muss konfiguriert werden (PostEndpoint: `/wp-json/dgptm-workshop/v1/stripe-webhook`).
- **Zoho CRM** — Schreibzugriff auf `Veranstal_X_Contacts` + `Contacts`; Lese-/Blueprint-Transition-Rechte.
- **EIV-Fobi** — Aktuell kein direkter Touchpoint (VNR-Erfassung erst in v2).

## 9. Nicht im Scope (v1)

- VNR/EIV-Fobi-Erfassung im Buchungsformular → v2
- Gruppenanmeldung mit einem gemeinsamen Zahler für mehrere Personen (geht bereits indirekt)
- Conditional-Field-Logik pro Ticket-Typ (Pflichtfelder variabel)
- Webinar- und Kongress-Buchung → spätere Module, die den Core wiederverwenden
- Automatische Migration bestehender Backstage-Buchungen

## 10. Offene Punkte zur Entscheidung

1. **Storno-Frist**: Einheitlich (z.B. 14 Tage) oder pro Event konfigurierbar? → Vorschlag: neues Feld `Storno_Frist_Tage` am Event.
2. **Refund-Politik nach Frist**: Gar kein Refund, 50%, nach Kulanz? → braucht AGB-Abstimmung.
3. **Blueprint-Status-Wording**: Wie sollen die neuen Status heißen? → Vorschlag: „Zahlung ausstehend", „Warteliste", „Nachrücker – Zahlung ausstehend", „Storniert".
4. **EduGrant-Verknüpfung**: Nur Hinweis/Link auf der Karte, oder integrierter Flow „Ich beantrage EduGrant zu dieser Buchung"? v1-Vorschlag: nur Hinweis.
5. **Stripe-Konto**: Das Konto der Gesellschaft wird verwendet? Oder separates Sub-Konto? → Finanz-/Buchhaltungs-Entscheidung.
6. **Teilnahme-Zertifikat** nach Workshop: Bereits in diesem Modul, oder weiterhin durch `fortbildung`-Post-Type (manuell)? → v1-Vorschlag: out of scope, kommt via bestehendem `fortbildung`-Flow.

## 11. Zeitplan (Schätzung)

| Phase | Aufwand |
|---|---|
| Implementierung Core (Events, Booking, Stripe, CRM, Mail) | ~3 Personentage |
| Frontend (Shortcodes, Templates, JS-Progressive-Form) | ~1,5 Personentage |
| Warteliste, Storno, Webhook-Edge-Cases | ~1,5 Personentage |
| Test auf Staging + Anpassungen | ~1 Personentag |
| **Summe** | **~7 Personentage** |

---

*Dokument erstellt: 2026-04-22, Sebastian Melzer. Status: Zwischenstand zur internen Entscheidung, noch keine Umsetzung begonnen.*
