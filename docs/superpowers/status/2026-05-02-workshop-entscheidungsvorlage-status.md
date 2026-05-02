# Status-Check: Entscheidungsvorlage Workshop-Booking-Modul
**Datum:** 02.05.2026  
**Erstellt für:** Sebastian Melzer (s.melzer@dgptm.de)  
**Modul:** `modules/business/workshop-booking/`  
**Shortcode (Mitgliederbereich):** `[dgptm_workshop_entscheidungsvorlage]`

---

## Erinnerung an Sebastian

Dieser Report fasst den aktuellen Stand der Entscheidungsvorlage zusammen. Da kein direkter
Datenbankzugriff auf perfusiologie.de möglich ist, zeigt er nur den **Dokumentenstand** aus
dem Template. Für den Live-Stand (Freigaben, Kommentare, Zustimmungen) bitte den Export nutzen.

**→ Falls in 7 Tagen (bis 09.05.2026) keine oder unvollständige Freigaben vorliegen: bitte aktiv
eine Erinnerungs-Mail an den Vorstand schicken.**

---

## Live-Check: So holst du die aktuellen Zahlen

1. WP-Admin auf [perfusiologie.de](https://www.perfusiologie.de/wp-admin) öffnen.
2. Eine Seite/Post mit dem Shortcode aufrufen (Mitgliederbereich) **oder** als Admin direkt:
   - Shortcode `[dgptm_workshop_entscheidungsvorlage_export]` auf einer Admin-Seite einbauen
     → dieser rendert einen Export-Button (nur für `manage_options`).
3. Export kopieren — enthält:
   - Gesamtfreigaben (Wer hat die Vorlage als Ganzes freigegeben?)
   - Zeilen-Zustimmungen pro Vorschlag (`dgptm_wsb_evl_row_approvals`)
   - Alle Kommentare (`dgptm_wsb_evl_comments`)
4. Direkte WP-Optionen (Fallback via WP-CLI auf dem Server):
   ```bash
   wp option get dgptm_wsb_evl_approvals --format=json
   wp option get dgptm_wsb_evl_row_approvals --format=json
   wp option get dgptm_wsb_evl_comments --format=json
   ```

---

## Abschnitt 3: Alle Vorschläge (Quick-Check)

> Prüfe im Live-Export, ob bei einem der folgenden Vorschläge ein **Widerspruch**
> (Kommentar ohne Zustimmung) vorliegt. Die `row_id` zum Nachschlagen ist in Klammern.

### Buchungs-Logik

| # | Vorschlag (Kurzfassung) | row_id |
|---|--------------------------|--------|
| 1 | **Scope V1:** Workshops und Webinare — Kongresse/Sachkundekurse bleiben in Backstage; Bausteine zukunftsfähig | `entscheidung-row-1` |
| 2 | **Login-Pflicht:** Mitglieder 1-Klick, Gäste kurzes Formular | `entscheidung-row-2` |
| 3 | **Zahlung via Stripe** (eigenes Unterkonto für Workshops) — PayPal/Apple Pay/SEPA via Stripe inklusive, keine separate PayPal-Anbindung | `entscheidung-row-3` |
| 4 | **Gruppenbuchung:** Pro TN eigener Datensatz + eigene Bescheinigung; optional Sammel-Rechnung an Zahler (mit Kostenstelle) | `entscheidung-row-4` |
| 5 | **Pflichtfelder:** Vorname, Nachname, E-Mail, Rechnungsadresse, Studierendenstatus; Nachweis bei Studi-Tarif via Upload (Prüfung durch Geschäftsstelle) | `entscheidung-row-5` |
| 6 | **Mitglieder-Erkennung:** E-Mail-Abgleich mit CRM; kein Treffer → neuer Kontakt | `entscheidung-row-6` |
| 7 | **Einbindung:** Zwei frei platzierbarer Shortcode-Platzhalter | `entscheidung-row-7` |
| 8 | **Warteliste:** Automatisch bei vollem Workshop; Nachrücker:in hat 24h | `entscheidung-row-8` |
| 9 | **Storno:** Option 1: bis 6 Wochen vorher, 10 %/max. 35 € Gebühr. Option 2: Übertragung bis drittletzten Werktag, 20 %/max. 70 € — AGB §6 | `entscheidung-row-9` |
| 10 | **E-Mails:** Bestätigung, Warteliste, Nachrück-Einladung, Storno, Termin-Verlegung + Reminder 7 Tage/1 Tag vor Workshop | `entscheidung-row-10` |
| 11 | **Rabattcodes:** In V1 nicht aktiv, aber technisch vorbereitet (zentrale Pflege im Modul/CRM) | `entscheidung-row-11` |
| 12 | **Architektur:** Austauschbare Bausteine für spätere Wiederverwendung (Webinare, Kongresse) | `entscheidung-row-12` |
| 12a | **Mehrsprachigkeit DE/EN:** Von Anfang an zweisprachig; bei englischsprachigen Veranstaltungen automatisch englische Texte | `entscheidung-row-12a` |

### Tickets, QR-Code und Mitgliederbereich

| # | Vorschlag (Kurzfassung) | row_id |
|---|--------------------------|--------|
| 13 | **Backstage-Spiegelung:** Alle Buchungen ins eigene CRM, einheitliche Sicht im Mitgliederbereich | `entscheidung-row-13` |
| 14 | **Ticketnummern:** Format wie Backstage, Präfix `99999` zur eindeutigen Unterscheidung | `entscheidung-row-14` |
| 15 | **QR-Code:** Auf jedem Ticket, Scan per Smartphone am Workshop-Tag | `entscheidung-row-15` |
| 16 | **Mitgliederbereich "Meine Tickets":** Download Teilnahmebescheinigung erst nach bestätigter Anwesenheit + Veranstaltungsende | `entscheidung-row-16` |
| 17 | **Nicht-Mitglieder:** Persönlicher Link per E-Mail ohne Login-Pflicht, Ablauf nach Workshop-Ende + 30 Tage | `entscheidung-row-17` |
| 18 | **Auto-Zuordnung:** Buchung ohne Login → E-Mail-Abgleich → automatisch zum CRM-Mitgliedskontakt zugeordnet; Studierendenstatus nie automatisch übernommen | `entscheidung-row-18` |

### Teilnahmezertifikate

| # | Vorschlag (Kurzfassung) | row_id |
|---|--------------------------|--------|
| 19 | **Automatische Bescheinigung:** Nur nach Veranstaltungsende + bestätigter Anwesenheit; Engine aus vimeo-webinare | `entscheidung-row-19` |
| 20 | **Layout konfigurierbar:** Standard-Layout oder eigenes Layout pro Workshop, Geschäftsstelle pflegt | `entscheidung-row-20` |
| 21 | **Externe Designer:innen:** Persönlicher Link ohne WP-Zugang, nur Lesezugriff auf Layout-Editor, kein Zugriff auf TN-Daten | `entscheidung-row-21` |

### Erweiterte Funktionen *(nachträglich ergänzt)*

| # | Vorschlag (Kurzfassung) | row_id |
|---|--------------------------|--------|
| 22 | **Variable Pflichtfelder pro Ticket-Typ:** z. B. Firmenname für Sponsorenkarten, Funktionsbestätigung für Ehrenamts-Tickets | `entscheidung-row-22` |
| 23 | **Webinar-Modul direkt verbunden:** vimeo-webinare von Anfang an über denselben Buchungskern; Vimeo für On-Demand-Aufzeichnung, Live via Zoho Meetings; Anti-Skip-Tracking für Nachhol-Bescheinigung | `entscheidung-row-23` |

---

## Abschnitt 10: Offene Fragen — alle gelöst (30.04.2026)

Alle 18 formalen offenen Fragen wurden am **30.04.2026** entschieden. Nachfolgend der Stand
zur Dokumentation — und als Checkliste, ob der Vorstand die Beschlüsse kennt und mitträgt.

| # | Frage | Beschluss (30.04.2026) | row_id |
|---|-------|------------------------|--------|
| 1 | Storno-Frist | Default 42 Tage (6 Wochen), AGB-konform; pro Workshop 28–42 Tage | `offen-row-1` |
| 2 | Storno nach Frist | Gemäß AGB §6 keine Erstattung; Härtefall über Geschäftsstelle; Übertragung möglich | `offen-row-2` |
| 3 | Anmelde-Status-Bezeichnungen | 8 Status-Werte (Zahlung ausstehend → Teilgenommen/Nicht teilgenommen) | `offen-row-3` |
| 4 | Edugrant-Integration | Nur Hinweis + Link; Verfügbarkeit/Plätze im CRM gepflegt, Prüfung durch Vorstand | `offen-row-4` |
| 5 | Backstage-Spiegelung (Technik) | Cron alle 15 Min, kein Echtzeit-Sync | `offen-row-5` |
| 6 | Backstage-Migration | Nur künftige + aktive Tickets; keine Massenmigration historischer Daten | `offen-row-6` |
| 7 | Ticketnummer-Format | Wie Backstage + Präfix `99999` | `offen-row-7` |
| 8 | Gültigkeit persönlicher Links | Bis Workshop-Ende + 30 Tage | `offen-row-8` |
| 9 | Wer darf Designer:innen einladen? | Nur Geschäftsstelle | `offen-row-9` |
| 10 | Standard-Layout Bescheinigung | Wird im Projektverlauf mit Geschäftsstelle entwickelt | `offen-row-10` |
| 11 | Anwesenheitserfassung | Präsenz: QR-Scan; Online: automatisch via Live-Tool-Export; On-Demand: Anti-Skip | `offen-row-11` |
| 12 | Online-Tool oder App? | V1 als Web-Tool; native App nur bei nachgewiesenem Bedarf | `offen-row-12` |
| 13 | Sammel-Rechnung bei Mehrfach-Tickets | **Ja, in V1** — wählbar: Einzelrechnungen pro TN oder Sammel-Rechnung an Zahler | `offen-row-13` |
| 14 | Rechnungserstellung Zoho Books | Vorlage frei definierbar; Nummernkreis aus Books; Edugrant separat verbucht | `offen-row-14` |
| 15 | Bescheinigung bei mehrtägigen Veranstaltungen | Eine Sammelbescheinigung nach Veranstaltungsende, anwesende Tage + FoBi-Punkte ausgewiesen | `offen-row-15` |
| 16 | Termin-Verlegung — Storno-Recht? | AGB §4 maßgeblich; Standard-Storno gilt; Sonderkulanz über Geschäftsstelle | `offen-row-16` |
| 17 | Studierenden-Nachweis | Pflicht-Upload Immatrikulationsbescheinigung, Prüfung Geschäftsstelle, Differenz-Nachforderung bei Ablehnung | `offen-row-17` |
| 18 | Anwesenheits-Schwelle für Bescheinigung | Frei pro Workshop konfigurierbar (z. B. 50 %, 80 %, 100 %) | `offen-row-18` |

---

## Handlungsempfehlungen

1. **Freigaben prüfen (jetzt):** Live-Export abrufen (Anleitung oben). Wie viele Vorstandsmitglieder
   haben die Gesamtvorlage freigegeben? Ziel: alle relevanten Stakeholder.

2. **Keine Freigabe bis 09.05.2026:** Erinnerungs-Mail an Vorstand manuell versenden — Hinweis
   auf Shortcode `[dgptm_workshop_entscheidungsvorlage]` im Mitgliederbereich.

3. **Widersprüche prüfen:** Gibt es Zeilen mit Kommentaren, aber ohne Zustimmung? Besonders
   kritisch bei Vorschlägen 3 (Stripe/Zahlungsmodell), 9 (Storno-Konditionen) und 4 (Sammel-Rechnung).

4. **Zoho Books Abstimmung:** Rechnungsvorlage und Nummernkreis müssen mit der Geschäftsstelle
   abgestimmt werden, bevor die Implementierung beginnt (Abschnitt 6, offene Frage 14).

5. **Implementierungsstart:** Erst wenn alle Freigaben vorliegen und keine offenen Widersprüche
   mehr existieren.

---

## Technische Referenz

| Option | Inhalt |
|--------|--------|
| `dgptm_wsb_evl_approvals` | Gesamtfreigaben `[{user_id, user_name, timestamp}]` |
| `dgptm_wsb_evl_row_approvals` | `{row_id: {user_id: {user_name, timestamp}}}` |
| `dgptm_wsb_evl_comments` | `[{id, user_id, user_name, section, text, timestamp, status}]` |

Template: `modules/business/workshop-booking/templates/entscheidungsvorlage-dokument.php`  
Klasse: `class-entscheidungsvorlage.php` (im selben Modulverzeichnis)
