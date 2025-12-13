# Quick Reference - Completion Debugging

## 🔍 Wo ist das Problem?

### ✅ ERFOLG sieht so aus:

**Browser Console (F12):**
```
VW Progress Check: {..., progress: "90.00", willComplete: true}
VW: COMPLETION TRIGGERED!
```

**debug.log:**
```
VW Complete Webinar - User: X, Webinar: Y
VW Create Fortbildung - Post created: Z
VW Complete Webinar - SUCCESS!
```

**Sichtbar:**
- ✅ Notification "Glückwunsch!"
- ✅ Seite lädt neu
- ✅ Banner "Abgeschlossen"
- ✅ Button "Zertifikat herunterladen"
- ✅ E-Mail erhalten

---

## ❌ Fehler-Diagnose (5 Sekunden)

| Was Sie sehen | Problem liegt in | Nächster Schritt |
|---------------|------------------|------------------|
| **Keine Console-Logs** | Frontend/JavaScript | → Vimeo SDK geladen? `typeof Vimeo` in Console eingeben |
| **Console zeigt `duration: 0`** | Vimeo-Video | → Vimeo-ID prüfen, Video auf Vimeo.com testen |
| **"COMPLETION TRIGGERED!" aber nichts passiert** | AJAX-Call | → Network Tab: admin-ajax.php Status prüfen (403/500?) |
| **Console OK, aber keine Backend-Logs** | Nonce/Login | → Seite neu laden, als User einloggen |
| **Backend: "Webinar not found"** | Webinar-ID | → WordPress Admin: Webinar existiert? |
| **Backend: "wp_insert_post failed"** | Post Type | → Fortbildung-Modul aktiv? Permalinks flushen? |
| **Backend: "Failed to generate certificate"** | FPDF Library | → `/libraries/fpdf/fpdf.php` vorhanden? |
| **Backend: "Email sent: No"** | wp_mail() | → Einstellungen: E-Mail aktiviert? SMTP konfiguriert? |

---

## 🔧 Häufigste Lösungen

### Problem: Completion triggert nicht
```javascript
// In Browser Console prüfen:
console.log({
  isLoggedIn: $('.vw-player-container').data('user-logged-in'),
  completion: $('.vw-player-container').data('completion'),
  webinarId: $('.vw-player-container').data('webinar-id')
});
```
**Fix:** Wenn `user-logged-in` nicht "true" → Einloggen!

---

### Problem: Nonce-Fehler (403)
**Symptom:** Network Tab zeigt 403 Forbidden
**Fix:** Seite neu laden (F5)

---

### Problem: Post Type fehlt
**Symptom:** Backend-Log: "wp_insert_post failed"
**Fix:**
1. WordPress Admin → Einstellungen → Permalinks
2. "Änderungen speichern" klicken
3. Fortbildung-Modul aktiviert?

---

### Problem: FPDF nicht gefunden
**Symptom:** Backend-Log: "Failed to generate certificate"
**Fix:** FPDF Library in `/dgptm-plugin-suite/libraries/fpdf/` installieren

---

### Problem: E-Mail wird nicht verschickt
**Symptom:** Backend-Log: "Email sent: No"
**Fix:**
1. Webinare → Einstellungen → E-Mail aktiviert? ✓
2. Plugin "WP Mail SMTP" installieren + konfigurieren

---

## 📊 System-Voraussetzungen

Damit Completion funktioniert, müssen diese Bedingungen erfüllt sein:

### Frontend:
- ✅ User eingeloggt (`is_user_logged_in()` = true)
- ✅ Vimeo Player SDK geladen (CDN)
- ✅ Vimeo-Video verfügbar (nicht privat/gelöscht)
- ✅ Video-Dauer > 0
- ✅ `data-completion` Attribut gesetzt (z.B. 90)

### Backend:
- ✅ Post Type 'fortbildung' registriert
- ✅ ACF Plugin aktiv (Advanced Custom Fields)
- ✅ ACF Feldgruppe "Fortbildung" mit korrekten Feldern
- ✅ FPDF Library vorhanden
- ✅ Upload-Verzeichnis beschreibbar (`/wp-content/uploads/webinar-certificates/`)
- ✅ wp_mail() funktioniert

---

## 🎯 Test-Prozedur (2 Minuten)

1. **Debug aktivieren** (wp-config.php):
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```

2. **Browser Console öffnen** (F12)

3. **Als User einloggen**

4. **Webinar öffnen** (`/wissen/webinar/{id}`)

5. **Video abspielen** bis Bestehensgrenze (z.B. 90%)

6. **Prüfen:**
   - Console: "VW Progress Check" alle 1 Sekunde?
   - Console: "COMPLETION TRIGGERED!" bei 90%?
   - Notification: "Glückwunsch!"?
   - debug.log: "VW Complete Webinar - SUCCESS!"?

---

## 📂 Log-Dateien

| Datei | Inhalt | Öffnen mit |
|-------|--------|------------|
| `/wp-content/debug.log` | Backend-Fehler, PHP-Logs | Texteditor |
| Browser Console (F12) | Frontend-Logs, JavaScript | Browser DevTools |
| Network Tab (F12) | AJAX-Calls, HTTP-Status | Browser DevTools |

---

## 🚨 Kritische Fehler

Diese Fehler verhindern Completion **komplett**:

| Fehler | Symptom | Fix |
|--------|---------|-----|
| **Vimeo SDK nicht geladen** | Console: `Uncaught ReferenceError: Vimeo is not defined` | CDN-URL prüfen, Netzwerk prüfen |
| **User nicht eingeloggt** | Console: `isLoggedIn: false` | Einloggen |
| **Video-Dauer = 0** | Console: `duration: 0` | Vimeo-ID prüfen |
| **Nonce abgelaufen** | Network: 403 Forbidden | Seite neu laden |
| **Post Type fehlt** | Backend: "wp_insert_post failed" | Fortbildung-Modul aktivieren |

---

## 💡 Pro-Tipps

### Testen ohne 30 Minuten Video schauen:
Ändern Sie temporär die Bestehensgrenze:
1. Webinare → Webinar bearbeiten
2. "Bestehensgrenze (%)" → auf 5% setzen
3. Testen
4. Zurück auf 90% setzen

### Fake-Completion für Backend-Test:
Browser Console:
```javascript
completeWebinar(123); // Ihre Webinar-ID
```

### Debug-Logs live verfolgen:
```bash
tail -f /wp-content/debug.log | grep "VW "
```

---

## 📞 Support-Checkliste

Wenn Sie Support anfordern, berichten Sie:

- [ ] WordPress Version: ___
- [ ] PHP Version: ___
- [ ] ACF Version: ___
- [ ] Browser + Version: ___
- [ ] Was sehen Sie in Console? (Screenshot)
- [ ] Was steht in debug.log? (Letzte 50 Zeilen)
- [ ] Network Tab: Status von admin-ajax.php? (Screenshot)
- [ ] Webinar-ID zum Testen: ___
- [ ] User-ID zum Testen: ___

---

**Version:** 1.2.4
**Datum:** 27. November 2025
**Siehe auch:** `DEBUG-COMPLETION.md`, `TESTING-GUIDE.md`
