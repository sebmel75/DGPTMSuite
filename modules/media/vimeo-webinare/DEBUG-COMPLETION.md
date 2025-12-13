# Debug-Anleitung - Automatische Completion

## Version 1.2.4 - Mit umfangreichem Logging

Diese Version enthält detailliertes Logging, um zu prüfen, warum die automatische Completion beim Erreichen der Bestehensgrenze nicht funktioniert.

---

## 🔍 Schritt-für-Schritt Debugging

### 1. WordPress Debug-Modus aktivieren

**wp-config.php bearbeiten:**
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

**Log-Datei:** `/wp-content/debug.log`

---

### 2. Browser Console öffnen

**Chrome/Firefox:**
- `F12` drücken
- Tab "Console" öffnen
- Filter auf "VW" setzen

---

### 3. Webinar bis zur Bestehensgrenze ansehen

**Was Sie sehen sollten:**

#### In der Browser Console (JavaScript):
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

**Wenn Sie das NICHT sehen:**
- Problem ist im Frontend (JavaScript)
- Prüfen Sie:
  - `data-completion` Attribut korrekt?
  - `data-user-logged-in="true"`?
  - Video-Dauer wurde geladen?

#### Im WordPress Debug-Log (Backend):
```
VW Complete Webinar - User: 123, Webinar: 456
VW Complete Webinar - Creating Fortbildung entry...
VW Create Fortbildung - Start: User 123, Webinar 456
VW Create Fortbildung - Webinar found: Test Webinar
VW Create Fortbildung - No existing entry, creating new...
VW Create Fortbildung - Points: 2.5, VNR: 12345
VW Create Fortbildung - Post created: 789
VW Create Fortbildung - Setting ACF fields...
VW Create Fortbildung - ACF fields set
VW Create Fortbildung - Completed. Fortbildung ID: 789
VW Complete Webinar - Fortbildung created: 789
VW Complete Webinar - Generating certificate...
VW Complete Webinar - Certificate generated: https://...
VW Complete Webinar - Sending email...
VW Complete Webinar - Email sent: Yes
VW Complete Webinar - SUCCESS!
```

**Wenn Sie das NICHT sehen:**
- AJAX-Call kam nicht an
- Oder Nonce-Fehler
- Oder User nicht eingeloggt

---

## 🐛 Häufige Probleme und Lösungen

### Problem 1: "VW Progress Check" erscheint nicht

**Ursache:** JavaScript-Tracking läuft nicht

**Prüfen:**
```javascript
// Browser Console
console.log('isLoggedIn:', container.data('user-logged-in'));
console.log('completionRequired:', container.data('completion'));
console.log('duration:', duration);
```

**Lösung:**
- Player-Template prüfen: `data-user-logged-in="true"`?
- Player-Template prüfen: `data-completion="90"`?
- Vimeo Player SDK geladen?

---

### Problem 2: "COMPLETION TRIGGERED!" aber nichts passiert

**Ursache:** AJAX-Call schlägt fehl

**Prüfen:**
```javascript
// Browser Console → Network Tab
// Suchen nach: admin-ajax.php?action=vw_complete_webinar
// Response prüfen
```

**Mögliche Fehler:**
- **403 Forbidden** → Nonce abgelaufen (Seite neu laden)
- **500 Internal Server Error** → PHP-Fehler (debug.log prüfen)
- **{"success":false}** → Siehe error message

---

### Problem 3: Fortbildung-Post wird nicht erstellt

**Debug-Log zeigt:**
```
VW Create Fortbildung - ERROR: wp_insert_post failed: ...
```

**Mögliche Ursachen:**
1. **Post Type 'fortbildung' existiert nicht**
   - Prüfen: Fortbildung-Modul aktiviert?
   - Prüfen: `get_post_types()` enthält 'fortbildung'?

2. **Berechtigungsproblem**
   - User hat keine Berechtigung, Posts zu erstellen
   - Lösung: `'post_author' => 1` (Admin-User)

3. **ACF Feldgruppe fehlt**
   - Prüfen: ACF Plugin aktiv?
   - Prüfen: Feldgruppe "Fortbildung" existiert?

**Lösung:**
```php
// In wp-admin → ACF → Field Groups
// Feldgruppe "Fortbildung" prüfen
// Post Type = "fortbildung" zugeordnet?
```

---

### Problem 4: ACF-Felder werden nicht gesetzt

**Debug-Log zeigt:**
```
VW Create Fortbildung - Post created: 789
VW Create Fortbildung - Setting ACF fields...
VW Create Fortbildung - ACF fields set
```

**Aber Felder sind leer?**

**Prüfen:**
```php
// Direkt nach update_field() testen:
$test = get_field('user', $fortbildung_id);
error_log('Test user field: ' . print_r($test, true));
```

**Mögliche Ursachen:**
1. ACF Feldnamen stimmen nicht überein
2. ACF Version zu alt (< 5.0)
3. ACF Felder nicht für Post Type registriert

**Feldnamen prüfen:**
```php
// Soll sein (laut Ihrer Struktur):
'user', 'date', 'location', 'type', 'points', 'vnr',
'token', 'freigegeben', 'freigabe_durch', 'freigabe_mail'
```

---

### Problem 5: Zertifikat wird nicht generiert

**Debug-Log zeigt:**
```
VW Complete Webinar - ERROR: Failed to generate certificate
```

**Ursache:** FPDF Library nicht gefunden

**Prüfen:**
```php
$fpdf_path = DGPTM_SUITE_PATH . 'libraries/fpdf/fpdf.php';
if (!file_exists($fpdf_path)) {
    error_log('FPDF not found at: ' . $fpdf_path);
}
```

**Lösung:**
- FPDF Library in `/dgptm-plugin-suite/libraries/fpdf/` installieren
- Oder Pfad anpassen

---

### Problem 6: E-Mail wird nicht verschickt

**Debug-Log zeigt:**
```
VW Complete Webinar - Email sent: No
```

**Mögliche Ursachen:**

1. **E-Mail deaktiviert in Einstellungen**
   - Webinare → Einstellungen → E-Mail aktiviert?

2. **wp_mail() funktioniert nicht**
   - Test-E-Mail verschicken:
   ```php
   wp_mail('test@example.com', 'Test', 'Test');
   ```

3. **SMTP nicht konfiguriert**
   - Plugin installieren: "WP Mail SMTP"
   - SMTP-Server konfigurieren

**Debug wp_mail():**
```php
add_action('wp_mail_failed', function($error) {
    error_log('Mail Error: ' . $error->get_error_message());
});
```

---

## 📋 Checkliste für vollständiges Debugging

### Frontend (Browser Console)
- [ ] `VW Progress Check` erscheint jede Sekunde
- [ ] `duration` ist > 0
- [ ] `progress` steigt an
- [ ] `willComplete: true` bei Erreichen der Grenze
- [ ] `COMPLETION TRIGGERED!` erscheint
- [ ] AJAX-Call in Network-Tab sichtbar
- [ ] AJAX Response = `{"success":true}`

### Backend (debug.log)
- [ ] `VW Complete Webinar - User: X, Webinar: Y`
- [ ] `VW Create Fortbildung - Webinar found: ...`
- [ ] `VW Create Fortbildung - Post created: X`
- [ ] `VW Create Fortbildung - ACF fields set`
- [ ] `VW Complete Webinar - Certificate generated: ...`
- [ ] `VW Complete Webinar - Email sent: Yes`
- [ ] `VW Complete Webinar - SUCCESS!`

### WordPress Admin
- [ ] Neuer Post in "Fortbildungen" vorhanden
- [ ] ACF-Felder korrekt befüllt
- [ ] PDF in `/wp-content/uploads/webinar-certificates/`
- [ ] User Meta `_vw_completed_{id}` = true
- [ ] Seite zeigt "Webinar abgeschlossen!" Banner

### E-Mail
- [ ] E-Mail im Posteingang
- [ ] Zertifikat-Link funktioniert
- [ ] PDF lässt sich herunterladen

---

## 🔧 Manuelle Tests

### Test 1: AJAX-Handler direkt aufrufen

**Browser Console:**
```javascript
jQuery.ajax({
    url: vwData.ajaxUrl,
    type: 'POST',
    data: {
        action: 'vw_complete_webinar',
        nonce: vwData.nonce,
        webinar_id: 123 // Ihre Webinar-ID
    },
    success: function(response) {
        console.log('Success:', response);
    },
    error: function(xhr, status, error) {
        console.log('Error:', error);
    }
});
```

### Test 2: Fortbildung manuell erstellen

**PHP (in functions.php temporär):**
```php
add_action('init', function() {
    if (isset($_GET['test_fortbildung'])) {
        $instance = DGPTM_Vimeo_Webinare::get_instance();

        // Reflection um private Methode aufzurufen
        $reflection = new ReflectionClass($instance);
        $method = $reflection->getMethod('create_fortbildung_entry');
        $method->setAccessible(true);

        $result = $method->invoke($instance, get_current_user_id(), 123);

        echo 'Fortbildung ID: ' . $result;
        die();
    }
});
```

**Aufrufen:** `/?test_fortbildung=1`

---

## 📞 Support-Informationen sammeln

Wenn Sie Support anfordern, senden Sie bitte:

1. **Browser Console Log** (komplett)
2. **WordPress debug.log** (letzten 100 Zeilen)
3. **WordPress Version**
4. **PHP Version**
5. **ACF Version**
6. **Aktive Plugins**
7. **Webinar-ID zum Testen**

**Log exportieren:**
```bash
tail -n 100 /wp-content/debug.log > debug-export.txt
```

---

## ✅ Erfolgreiche Completion sieht so aus:

**Browser Console:**
```
VW Progress Check: {watched: 1620, duration: 1800, progress: "90.00", required: 90, willComplete: true}
VW: COMPLETION TRIGGERED!
```

**debug.log:**
```
[27-Nov-2025 12:34:56] VW Complete Webinar - User: 123, Webinar: 456
[27-Nov-2025 12:34:56] VW Create Fortbildung - Start: User 123, Webinar 456
[27-Nov-2025 12:34:56] VW Create Fortbildung - Webinar found: Test Webinar
[27-Nov-2025 12:34:56] VW Create Fortbildung - Post created: 789
[27-Nov-2025 12:34:56] VW Create Fortbildung - ACF fields set
[27-Nov-2025 12:34:56] VW Complete Webinar - Certificate generated: https://...
[27-Nov-2025 12:34:56] VW Complete Webinar - Email sent: Yes
[27-Nov-2025 12:34:56] VW Complete Webinar - SUCCESS!
```

**Ergebnis:**
- ✅ Neuer Fortbildung-Post
- ✅ PDF generiert
- ✅ E-Mail verschickt
- ✅ Seite zeigt Completion-Banner

---

**Viel Erfolg beim Debugging!** 🚀
