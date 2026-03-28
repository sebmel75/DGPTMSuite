# Testing-Anleitung - Automatische Completion (v1.2.4)

## 🎯 Schnelltest: Funktioniert die automatische Completion?

### Vorbereitung (2 Minuten)

1. **WordPress Debug aktivieren** - In `wp-config.php` einfügen:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

2. **Browser Console öffnen**
   - Chrome/Firefox: `F12` drücken
   - Tab "Console" öffnen

3. **Als User einloggen** (wichtig!)

4. **Webinar öffnen** unter `/wissen/webinar/{id}`

---

## 📊 Was Sie sehen sollten

### ✅ **Erfolgreicher Test**

#### In der Browser Console (alle 1 Sekunde):
```javascript
VW Progress Check: {
  watched: 1620,
  duration: 1800,
  progress: "90.00",
  required: 90,
  willComplete: true
}
VW: COMPLETION TRIGGERED!
```

#### Im WordPress Debug-Log (`/wp-content/debug.log`):
```
[27-Nov-2025 12:34:56] VW Complete Webinar - User: 123, Webinar: 456
[27-Nov-2025 12:34:56] VW Complete Webinar - Creating Fortbildung entry...
[27-Nov-2025 12:34:56] VW Create Fortbildung - Start: User 123, Webinar 456
[27-Nov-2025 12:34:56] VW Create Fortbildung - Webinar found: Test Webinar
[27-Nov-2025 12:34:56] VW Create Fortbildung - No existing entry, creating new...
[27-Nov-2025 12:34:56] VW Create Fortbildung - Points: 2.5, VNR: 12345
[27-Nov-2025 12:34:56] VW Create Fortbildung - Post created: 789
[27-Nov-2025 12:34:56] VW Create Fortbildung - Setting ACF fields...
[27-Nov-2025 12:34:56] VW Create Fortbildung - ACF fields set
[27-Nov-2025 12:34:56] VW Create Fortbildung - Completed. Fortbildung ID: 789
[27-Nov-2025 12:34:56] VW Complete Webinar - Fortbildung created: 789
[27-Nov-2025 12:34:56] VW Complete Webinar - Generating certificate...
[27-Nov-2025 12:34:56] VW Complete Webinar - Certificate generated: https://...
[27-Nov-2025 12:34:56] VW Complete Webinar - Sending email...
[27-Nov-2025 12:34:56] VW Complete Webinar - Email sent: Yes
[27-Nov-2025 12:34:56] VW Complete Webinar - SUCCESS!
```

#### Sichtbare Änderungen:
- ✅ Notification: "Glückwunsch! Sie haben das Webinar erfolgreich abgeschlossen! 🎉"
- ✅ Seite lädt neu und zeigt Banner: "Webinar abgeschlossen!"
- ✅ Button "Zertifikat herunterladen" ist sichtbar
- ✅ E-Mail mit Zertifikat-Link im Posteingang
- ✅ Neuer Eintrag in WordPress Admin → Fortbildungen

---

## ❌ **Fehlerfälle erkennen**

### Problem A: Keine Console-Logs erscheinen

**Symptom:** Browser Console ist komplett leer (keine "VW Progress Check" Meldungen)

**Ursache:** JavaScript läuft nicht

**Prüfen in Browser Console:**
```javascript
// Folgendes eingeben und Enter drücken:
typeof Vimeo
// Sollte "function" zurückgeben, nicht "undefined"

typeof vwData
// Sollte "object" zurückgeben
```

**Mögliche Gründe:**
- Vimeo SDK nicht geladen
- JavaScript-Fehler blockiert Script
- Player-Container nicht gefunden

**Lösung:** Siehe `DEBUG-COMPLETION.md` → Problem 1

---

### Problem B: Console-Logs erscheinen, aber `duration: 0`

**Symptom:**
```javascript
VW Progress Check: {
  watched: 10,
  duration: 0,    // ← Problem!
  progress: "0.00",
  required: 90,
  willComplete: false
}
```

**Ursache:** Vimeo Player hat Dauer nicht geladen

**Mögliche Gründe:**
- Vimeo-ID ungültig
- Video ist privat/gelöscht
- Vimeo API-Fehler

**Lösung:**
- Vimeo-ID prüfen (unter Webinare → Webinar bearbeiten)
- Video auf Vimeo.com aufrufen, um Verfügbarkeit zu prüfen

---

### Problem C: Console zeigt "COMPLETION TRIGGERED!" aber nichts passiert

**Symptom:** Console zeigt Trigger, aber keine Notification und kein Reload

**Ursache:** AJAX-Call schlägt fehl

**Prüfen:**
1. Browser → Developer Tools → Network Tab öffnen
2. Filter: `admin-ajax.php`
3. Nach Trigger suchen nach Request mit `action=vw_complete_webinar`
4. Status prüfen:
   - **403 Forbidden** → Nonce abgelaufen (Seite neu laden)
   - **500 Internal Server Error** → PHP-Fehler (debug.log prüfen)
   - **200 OK** → Response prüfen (sollte `{"success":true}` sein)

**Lösung:** Siehe `DEBUG-COMPLETION.md` → Problem 2

---

### Problem D: Backend-Logs fehlen komplett

**Symptom:** Browser zeigt "COMPLETION TRIGGERED!", aber `debug.log` enthält KEINE "VW Complete Webinar" Einträge

**Ursache:** AJAX kommt nicht im Backend an

**Mögliche Gründe:**
- Nonce-Fehler (403 Forbidden)
- User nicht eingeloggt
- AJAX-URL falsch

**Prüfen:**
```javascript
// In Browser Console eingeben:
console.log(vwData.ajaxUrl);
// Sollte sein: "https://ihre-domain.de/wp-admin/admin-ajax.php"

console.log(vwData.nonce);
// Sollte eine lange Zeichenkette sein
```

**Lösung:** Seite neu laden (Nonce könnte abgelaufen sein)

---

### Problem E: Backend-Logs zeigen "ERROR: Webinar not found"

**Symptom:**
```
VW Create Fortbildung - Start: User 123, Webinar 456
VW Create Fortbildung - ERROR: Webinar not found
```

**Ursache:** Webinar-ID ist ungültig oder Webinar wurde gelöscht

**Lösung:**
- WordPress Admin → Webinare prüfen
- Webinar-ID korrekt?

---

### Problem F: Backend-Logs zeigen "ERROR: wp_insert_post failed"

**Symptom:**
```
VW Create Fortbildung - No existing entry, creating new...
VW Create Fortbildung - Points: 2.5, VNR: 12345
VW Create Fortbildung - ERROR: wp_insert_post failed: [Fehlermeldung]
```

**Ursache:** Post Type 'fortbildung' existiert nicht oder Berechtigungsproblem

**Lösung:**
- Prüfen ob Fortbildung-Modul aktiv ist
- WordPress Admin → Einstellungen → Permalinks → Speichern (Flush Rewrite Rules)

---

### Problem G: Backend-Logs zeigen "ERROR: Failed to generate certificate"

**Symptom:**
```
VW Complete Webinar - Fortbildung created: 789
VW Complete Webinar - Generating certificate...
VW Complete Webinar - ERROR: Failed to generate certificate
```

**Ursache:** FPDF-Library nicht gefunden

**Lösung:**
- Prüfen: `/dgptm-plugin-suite/libraries/fpdf/fpdf.php` existiert?
- Datei fehlt → FPDF Library installieren

---

### Problem H: E-Mail wird nicht verschickt

**Symptom:**
```
VW Complete Webinar - Certificate generated: https://...
VW Complete Webinar - Sending email...
VW Complete Webinar - Email sent: No
```

**Ursache:** `wp_mail()` funktioniert nicht oder E-Mail deaktiviert

**Lösung:**
1. **Einstellungen prüfen:** Webinare → Einstellungen → E-Mail aktiviert?
2. **wp_mail() testen:**
   ```php
   // In functions.php temporär einfügen:
   add_action('init', function() {
       if (isset($_GET['test_mail'])) {
           $result = wp_mail('ihre-email@example.com', 'Test', 'Test-Mail');
           echo $result ? 'Mail gesendet!' : 'Mail-Fehler!';
           die();
       }
   });
   // Dann aufrufen: /?test_mail=1
   ```
3. **SMTP konfigurieren:** Plugin "WP Mail SMTP" installieren

---

## 📋 Checkliste für erfolgreiche Completion

Haken Sie ab, was funktioniert:

### Frontend (Browser Console):
- [ ] "VW Progress Check" erscheint jede Sekunde
- [ ] `duration` ist > 0
- [ ] `progress` steigt von 0 bis 100
- [ ] `willComplete: true` wenn Grenze erreicht
- [ ] "COMPLETION TRIGGERED!" erscheint

### Backend (debug.log):
- [ ] "VW Complete Webinar - User: X, Webinar: Y"
- [ ] "VW Create Fortbildung - Webinar found: ..."
- [ ] "VW Create Fortbildung - Post created: X"
- [ ] "VW Create Fortbildung - ACF fields set"
- [ ] "VW Complete Webinar - Certificate generated: ..."
- [ ] "VW Complete Webinar - Email sent: Yes"
- [ ] "VW Complete Webinar - SUCCESS!"

### Sichtbare Ergebnisse:
- [ ] Notification im Browser
- [ ] Seite lädt neu
- [ ] Banner "Webinar abgeschlossen!" sichtbar
- [ ] Button "Zertifikat herunterladen" funktioniert
- [ ] E-Mail im Posteingang
- [ ] Neuer Post in WordPress Admin → Fortbildungen
- [ ] ACF-Felder korrekt befüllt

---

## 🚀 Quick-Test (30 Sekunden)

Wenn Sie **schnell** testen wollen, ob überhaupt etwas läuft:

1. Webinar-Seite öffnen
2. `F12` drücken → Console Tab
3. Video abspielen (1 Sekunde reicht)
4. **Schauen:** Erscheint "VW Progress Check"?

**Ja → JavaScript läuft!** Problem liegt im Backend.
**Nein → JavaScript läuft NICHT!** Problem liegt im Frontend.

---

## 📞 Support-Paket erstellen

Wenn nichts funktioniert, sammeln Sie diese Infos:

1. **Browser Console Log** - Rechtsklick → "Save as..." → console-log.txt
2. **WordPress debug.log** - Letzte 100 Zeilen:
   ```bash
   tail -n 100 /wp-content/debug.log > debug-export.txt
   ```
3. **System-Info:**
   - WordPress Version: ?
   - PHP Version: ?
   - ACF Version: ?
   - Browser: ?
4. **Webinar-ID zum Testen:** ?
5. **Screenshot** vom Player mit geöffneter Console

---

## ✅ Erfolg bestätigen

Wenn ALLE Checkboxen oben abgehakt sind:

**🎉 Gratulation! Die automatische Completion funktioniert einwandfrei.**

Sie können jetzt:
- Weitere Webinare testen
- Debug-Modus deaktivieren (wp-config.php)
- System produktiv nutzen

---

**Bei Fragen:** Siehe `DEBUG-COMPLETION.md` für detaillierte Troubleshooting-Schritte.
