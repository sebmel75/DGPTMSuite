# CRM-Setup für Workshop-Booking (Phase 1)

Vor V1-Go-Live müssen in Zoho CRM folgende Felder/Picklist-Werte existieren. Diese Setup-Schritte sind durch die Geschäftsstelle bzw. durch Zoho-Admin durchzuführen.

## 1. Modul `Veranstal_X_Contacts`

### Pflichtfelder (durch Modul gesetzt/gelesen)

| Feld-API-Name | Typ | Default | Notiz |
|---|---|---|---|
| `Quelle` | Picklist | leer | Werte: `Modul`, `Backstage`. **KRITISCH** — der Sync_Coordinator skippt alles ≠ `Modul` |
| `Anmelde_Status` | Picklist | leer | 8 Werte (siehe unten) |
| `Zahlungsstatus` | Picklist | `Ausstehend` | Werte: `Ausstehend`, `Bezahlt`, `Erstattet`, `Teilerstattet` |
| `Stripe_Charge_ID` | Single Line | leer | PaymentIntent-ID |
| `Stripe_Session_ID` | Single Line | leer | Checkout-Session-ID |
| `Books_Invoice_ID` | Single Line | leer | (für Phase 7) |
| `Last_Sync_At` | Date/Time | leer | wird vom Sync_Coordinator gesetzt |
| `Ticket_Type` | Single Line | leer | z.B. „Vollpreis", „Mitgliedspreis", „Studi" |
| `Price_EUR` | Decimal | 0 | Ticket-Preis pro TN |

### Anmelde-Status (Picklist `Anmelde_Status`)

Genau diese 8 Werte, in genau dieser Schreibweise:

```
Zahlung ausstehend
Angemeldet
Warteliste
Nachrücker:in – Zahlung ausstehend
Abgebrochen
Storniert
Teilgenommen
Nicht teilgenommen
```

> Der Wert `Nachrücker:in – Zahlung ausstehend` enthält einen Gedankenstrich (–, U+2013), keinen Bindestrich.

### Bestandsdaten markieren (einmalig)

Bestehende `Veranstal_X_Contacts`-Einträge, die per Backstage-Flow angelegt wurden, müssen vor Go-Live mit `Quelle = Backstage` markiert werden — sonst überschreibt der Sync_Coordinator diese ggf. fälschlich.

**Bulk-Update via Zoho:**
1. Modul Veranstal_X_Contacts → Filter: `Quelle is empty`
2. Mass Update → `Quelle = Backstage`

## 2. Modul `DGfK_Events`

| Feld-API-Name | Typ | Default | Notiz |
|---|---|---|---|
| `Storno_Frist_Tage` | Zahl | 42 | Self-Service-Storno-Frist in Tagen (28–42 empfohlen) |
| `Anwesenheits_Schwelle_Prozent` | Zahl | 80 | Mindest-Anwesenheit für Bescheinigung (Phase 4) |
| `EduGrant_Verfuegbar` | Bool | false | Phase 1: noch nicht ausgewertet |
| `EduGrant_Hoehe_EUR` | Zahl | leer | Förderhöhe pro Platz |
| `EduGrant_Plaetze_Gesamt` | Zahl | leer | Förderplatz-Kontingent |
| `EduGrant_Plaetze_Vergeben` | Zahl | 0 | wird vom Modul hochgezählt |
| `Verantwortliche_Person` | Lookup → Contacts | leer | Phase 1: noch nicht genutzt |
| `Ticket_Layout` | Lookup → Layout-Tabelle | leer | Phase 4: Bescheinigungs-Layout |
| `Sprache` | Picklist | `DE` | `DE`, `EN`. Phase 1: nur DE |

## 3. Stripe-Konfiguration (WordPress-Optionen)

| Option | Pflicht | Quelle |
|---|---|---|
| `dgptm_wsb_stripe_secret_key` | ja | Stripe-Dashboard → API-Keys |
| `dgptm_wsb_stripe_webhook_secret` | ja | Stripe-Dashboard → Webhooks → Endpoint Secret |
| `dgptm_wsb_stripe_account_id` | optional | für eigenes Stripe-Unterkonto (Connected Account); leer = Hauptkonto |

Setzen via WP-CLI:
```bash
wp option update dgptm_wsb_stripe_secret_key "sk_test_..." 
wp option update dgptm_wsb_stripe_webhook_secret "whsec_..."
```

## 4. Stripe-Webhook konfigurieren

Im Stripe-Dashboard unter **Developers → Webhooks → Add endpoint**:

- URL: `https://perfusiologie.de/wp-json/dgptm-workshop/v1/stripe-webhook`
- Events:
  - `checkout.session.completed`
  - `checkout.session.expired`
  - `charge.refunded`
- Webhook-Secret in `dgptm_wsb_stripe_webhook_secret` eintragen

## 5. Cron-Jobs

WordPress-Cron läuft per Default auf jeder Seitenanfrage. Empfohlen für Produktion: System-Cron alle Minute auf `wp-cron.php`. Die beiden Modul-Crons laufen alle 15 Minuten:

- `dgptm_wsb_reconcile` — Drift-Erkennung Stripe vs. CRM
- `dgptm_wsb_pending_cleanup` — abgelaufene Stripe-Sessions aufräumen

Status prüfen via WP-CLI: `wp cron event list | grep dgptm_wsb`.

## 6. AGB-Audit-Trail

Die Tabelle `wp_dgptm_workshop_sync_log` ist append-only und dient als Audit-Trail (AGB §6 Abs. 3 Schriftform). Sie darf NICHT gelöscht werden ohne Rücksprache.

Aktuelle Einträge prüfen:
```sql
SELECT * FROM wp_dgptm_workshop_sync_log ORDER BY id DESC LIMIT 50;
```
