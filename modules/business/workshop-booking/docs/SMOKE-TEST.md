# Smoke-Test Phase 1

## Vorbereitung

- [ ] Modul aktiviert (DGPTMSuite-Admin → workshop-booking → aktivieren)
- [ ] DB-Tabellen vorhanden:
      ```sql
      SHOW TABLES LIKE 'wp_dgptm_workshop_%';
      -- erwartet: sync_log, drift_alerts, pending_bookings
      ```
- [ ] CRM-Felder gemäß `CRM-SETUP.md` angelegt
- [ ] Bestehende Backstage-Records markiert (`Quelle = Backstage`)
- [ ] Stripe-Test-Key gesetzt (`dgptm_wsb_stripe_secret_key`)
- [ ] Stripe-Webhook-Secret gesetzt (`dgptm_wsb_stripe_webhook_secret`)
- [ ] Webhook-Endpoint in Stripe-Dashboard konfiguriert
- [ ] Mindestens ein Workshop in `DGfK_Events` mit `From_Date` in Zukunft, `Event_Type='Workshop'`, `Maximum_Attendees > 0`
- [ ] Test-Seite mit Shortcode `[dgptm_workshops]` angelegt
- [ ] Bestätigungs-Seite mit Shortcode `[dgptm_workshops_success]` unter `/buchung-bestaetigt/` angelegt

## Test 1: Standard-Buchung mit Stripe-Zahlung

1. Test-Seite mit `[dgptm_workshops]` aufrufen → Workshop-Karten erscheinen
2. „Jetzt buchen" auf einer Karte → Dialog öffnet sich
3. Daten ausfüllen (Test-E-Mail, die noch NICHT in CRM existiert) → Submit
4. Erwartung: Redirect auf Stripe-Test-Checkout-Page
5. Mit Test-Karte `4242 4242 4242 4242`, Datum in Zukunft, beliebiger CVC zahlen
6. Erwartung: Redirect auf `/buchung-bestaetigt/?dgptm_wsb=success`
7. Bestätigungs-Mail prüfen (mit ICS-Anhang)
8. CRM prüfen: neuer `Veranstal_X_Contacts`-Eintrag mit:
   - `Quelle = Modul`
   - `Anmelde_Status = Angemeldet`
   - `Zahlungsstatus = Bezahlt`
   - `Stripe_Charge_ID` = PaymentIntent-ID gefüllt
9. `wp_dgptm_workshop_sync_log` prüfen:
   ```sql
   SELECT source, intent_blueprint_state, intent_payment_status, success, error_code
   FROM wp_dgptm_workshop_sync_log ORDER BY id DESC LIMIT 5;
   ```
   Erwartet: 2 Einträge mit success=1
   - source=`booking_init`, intent_blueprint=`Zahlung ausstehend`
   - source=`stripe_webhook`, intent_blueprint=`Angemeldet`
10. `wp_dgptm_workshop_pending_bookings` ist nach `checkout.session.completed` leer für diesen Contact

## Test 2: Backstage-Skip

1. In CRM manuell einen `Veranstal_X_Contacts`-Eintrag mit `Quelle = Backstage` anlegen, Status `Angemeldet`
2. WP-CLI:
   ```bash
   wp eval 'require_once "wp-content/plugins/dgptm-plugin-suite/modules/business/workshop-booking/includes/class-sync-intent.php";
   $i = new DGPTM_WSB_Sync_Intent("BACKSTAGE_ID", "Storniert", "Erstattet", "manual", [], "test-skip");
   $r = DGPTM_WSB_Sync_Coordinator::apply_intent($i);
   var_dump($r);'
   ```
   Erwartet: `success=true, error_code='source_skipped'`
3. CRM-Eintrag bleibt UNVERÄNDERT (Anmelde_Status weiterhin `Angemeldet`)
4. `wp_dgptm_workshop_sync_log` zeigt Eintrag mit `error_code = 'source_skipped'`

## Test 3: Drift-Reconciliation

1. CRM manuell: vorhandene Modul-Buchung (aus Test 1) auf `Anmelde_Status = Storniert` setzen, **OHNE** Stripe-Refund
2. WP-CLI: `wp cron event run dgptm_wsb_reconcile`
3. `wp_dgptm_workshop_drift_alerts` zeigt neuen Alert:
   ```sql
   SELECT code, severity, status, proposed_action FROM wp_dgptm_workshop_drift_alerts
   WHERE status='open' ORDER BY id DESC LIMIT 1;
   ```
   Erwartet: `code='manual_storno_without_refund'`, `severity='warning'`

## Test 4: Stripe-Webhook-Signaturprüfung

1. Curl-Aufruf ohne Signatur:
   ```bash
   curl -X POST https://staging.perfusiologie.de/wp-json/dgptm-workshop/v1/stripe-webhook \
        -H "Content-Type: application/json" \
        -d '{"type":"test"}'
   ```
   Erwartet: HTTP 401, `{"error":"invalid_signature"}`

2. Stripe-CLI (falls installiert):
   ```bash
   stripe trigger checkout.session.completed
   ```
   Erwartet: HTTP 200, sync_log bekommt Eintrag (success=0 weil Contact nicht existiert, aber Signatur akzeptiert)

## Test 5: Pending-Cleanup

1. Manuell in `wp_dgptm_workshop_pending_bookings` einen Eintrag mit
   `stripe_session_expires_at = '2020-01-01 00:00:00'` und einer existierenden
   Modul-Veranstal_X_Contacts-ID anlegen
2. Vorher: Modul-Eintrag steht auf `Zahlung ausstehend`
3. WP-CLI: `wp cron event run dgptm_wsb_pending_cleanup`
4. Erwartet:
   - pending_bookings-Eintrag gelöscht
   - CRM-Eintrag jetzt `Anmelde_Status = Abgebrochen`
   - `wp_dgptm_workshop_sync_log` zeigt source=`reconciliation`, reason=`pending_cleanup: stripe_session_expired`

## Test 6: Kostenloses Ticket (Freiticket)

1. Workshop mit `price_eur = 0` für alle Tickets anlegen
2. Buchen wie in Test 1
3. Erwartet: kein Stripe-Redirect; direkt auf `/buchung-bestaetigt/`
4. CRM-Eintrag direkt `Angemeldet/Bezahlt`, ohne `Stripe_Session_ID`
5. Bestätigungs-Mail mit ICS kommt sofort

## Test 7: Doppel-Buchung verhindern (4-Felder-E-Mail)

1. E-Mail-Adresse, die bereits im CRM als `Secondary_Email` eines Contacts hinterlegt ist, beim Buchen verwenden
2. Erwartet: KEINE Contact-Neuanlage; bestehender Contact wird verlinkt
3. Im CRM: nur ein Contact vorhanden (kein Duplikat)
4. `Veranstal_X_Contacts`-Eintrag verlinkt mit dem bestehenden Contact

---

**Bei Fehlern:** Logs prüfen:
- `wp-content/debug.log` (PHP-Fehler)
- `wp_dgptm_workshop_sync_log` (Sync-Fehler mit `success=0` und `error_code`)
- DGPTMSuite System-Logs (Admin-Bereich → Logs)
- Stripe-Dashboard → Logs (für Webhook-Failures)
