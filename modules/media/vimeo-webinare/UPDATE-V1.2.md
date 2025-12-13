# Update Guide - Version 1.2.0

## 🎯 Was ist neu?

**Version 1.2.0** behebt das kritische Problem, dass Benutzer durch Vorspulen den Fortschritt erreichen konnten.

### Hauptänderungen:

1. ✅ **Zeit-basiertes Tracking statt Position-basiert**
   - Misst tatsächlich angesehene Zeit (Sekunden)
   - Vorspulen zählt NICHT als angesehene Zeit

2. ✅ **Webinare für ALLE Benutzer verfügbar**
   - Nicht mehr login-required
   - Nicht eingeloggte können Videos ansehen
   - Nur eingeloggte erhalten Fortbildungspunkte

3. ✅ **Dual Storage System**
   - Eingeloggt: Fortschritt in Datenbank (User Meta)
   - Nicht eingeloggt: Fortschritt in Cookies (30 Tage)

4. ✅ **Login-Hinweis für nicht eingeloggte Benutzer**
   - Blauer Info-Banner mit Link zum Login
   - "Zum Eintrag in den Fortbildungsnachweis bitte einloggen"

---

## 🔧 Technische Änderungen

### Backend (dgptm-vimeo-webinare.php)

#### Neue Methoden:

```php
// Tatsächlich angesehene Zeit abrufen (Sekunden)
private function get_watched_time($user_id, $webinar_id)

// Video-Dauer aus Post Meta abrufen (gecacht)
private function get_video_duration($webinar_id)

// Cookie-Daten für nicht eingeloggte Benutzer abrufen
private function get_cookie_data($webinar_id)
```

#### Geänderte Methoden:

**`handle_webinar_page()`:**
- Entfernt: Login-Requirement
- Neu: Cookie-Daten für nicht eingeloggte laden
- Neu: `$user_id` kann jetzt `0` sein

**`ajax_track_progress()`:**
- Akzeptiert jetzt: `watched_time` (Sekunden) statt `position` (Prozent)
- Speichert: Addiert neue Zeit zur bestehenden (nicht überschreiben)
- Dual Storage: DB für eingeloggte, Cookie-Hinweis für nicht eingeloggte

**`get_user_progress()`:**
- Berechnet jetzt: `(watched_time / duration) * 100`
- Statt: Position-basierte Berechnung

---

### Frontend (script.js)

**Komplett umgeschrieben** für Interval-basiertes Tracking:

#### Neue Variablen:

```javascript
let watchedTime = 0;          // Kumulative angesehene Zeit (aus DB/Cookie)
let sessionWatchedTime = 0;   // Nur diese Session (wird zu watchedTime addiert)
let isPlaying = false;        // Video spielt gerade?
let trackingInterval = null;  // Interval-Timer
```

#### Tracking-Mechanismus:

```javascript
// 1-Sekunden-Interval während Video läuft
trackingInterval = setInterval(function() {
    if (isPlaying && !hasCompleted) {
        sessionWatchedTime += 1;
        watchedTime += 1;

        // Alle 10 Sekunden speichern
        if (sessionWatchedTime % 10 === 0) {
            saveProgress(sessionWatchedTime, duration);
        }
    }
}, 1000);
```

#### Anti-Skip Detection:

```javascript
player.on('seeked', function(data) {
    if (data.seconds > lastPosition + 1) {
        console.log('Forward seek detected - no time added');
    }
    lastPosition = data.seconds;
});
```

#### Cookie Support:

```javascript
function saveToCookie(watched, dur) {
    const cookieData = {
        watched_time: watched,
        progress: (watched / dur) * 100
    };
    const cookieName = 'vw_webinar_' + webinarId;
    document.cookie = cookieName + '=' + JSON.stringify(cookieData) +
        '; expires=' + expiryDate + '; path=/';
}
```

---

### Template (player.php)

#### Neue Elemente:

```php
<!-- Login-Hinweis für nicht eingeloggte -->
<?php if (!$user_id): ?>
    <div class="vw-login-notice">
        <span class="dashicons dashicons-info"></span>
        <strong>Hinweis:</strong> Zum Eintrag in den Fortbildungsnachweis bitte
        <a href="<?php echo wp_login_url(...); ?>">einloggen</a>.
    </div>
<?php endif; ?>
```

#### Geänderte Data-Attribute:

```html
<div class="vw-player-container"
     data-watched-time="<?php echo esc_attr($watched_time); ?>"
     data-user-logged-in="<?php echo $user_id ? 'true' : 'false'; ?>">
```

#### Angepasste UI:

```php
<!-- Zeit-Display statt Position-Display -->
Angesehene Zeit: <strong class="vw-watched-time-display">
    <?php echo gmdate('i:s', $watched_time); ?>
</strong> Min
```

---

### CSS (style.css)

#### Neue Styles:

```css
/* Login-Notice für nicht eingeloggte */
.vw-login-notice {
    background: #e3f2fd;
    border-left: 4px solid #2196F3;
    padding: 15px 20px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Angesehene Zeit Display */
.vw-watched-time-display {
    color: #2196F3;
    font-size: 18px;
}

/* Separator zwischen Zeit und Fortschritt */
.vw-separator {
    color: #ccc;
    margin: 0 8px;
}
```

---

## 📊 Datenstruktur

### User Meta (Eingeloggte Benutzer):

```php
meta_key: '_vw_watched_time_{webinar_id}'
meta_value: float (Sekunden, z.B. 542.5 = 9:02 Min)
```

### Post Meta (Video-Dauer Cache):

```php
meta_key: '_vw_video_duration'
meta_value: float (Sekunden, z.B. 1800 = 30 Min)
```

### Cookie (Nicht eingeloggte):

```javascript
cookie_name: 'vw_webinar_{webinar_id}'
cookie_value: JSON {
    "watched_time": 542.5,
    "progress": 30.14
}
expires: 30 Tage
```

---

## 🔄 Migration von 1.1.0 → 1.2.0

### Automatische Migration:

**Keine Aktion erforderlich!** Alte Daten bleiben erhalten:

1. Alte User Meta (`_vw_progress_{id}`) bleibt unberührt
2. Neue User Meta (`_vw_watched_time_{id}`) wird parallel angelegt
3. System berechnet Fortschritt aus beiden Quellen:
   - Falls `_vw_watched_time_{id}` vorhanden → nutzen
   - Falls nur `_vw_progress_{id}` vorhanden → umrechnen

### Manuelle Bereinigung (Optional):

Falls Sie alte Fortschrittsdaten entfernen möchten:

```sql
-- ACHTUNG: Nur ausführen wenn sicher!
DELETE FROM wp_usermeta
WHERE meta_key LIKE '_vw_progress_%';
```

---

## ⚙️ Wichtige Konzepte

### 1. Session vs. Kumulative Zeit

```javascript
sessionWatchedTime  // Nur diese Session (0 beim Page Load)
watchedTime         // Gesamt über alle Sessions
```

**Beispiel:**
- Benutzer schaut 5 Min → Speichert 300 Sek in DB
- Verlässt Seite, kommt zurück
- Lädt 300 Sek aus DB in `watchedTime`
- Schaut weitere 3 Min → `sessionWatchedTime = 180`
- Speichert `watchedTime + sessionWatchedTime = 480` in DB

### 2. Speicher-Strategie

```javascript
// Alle 10 Sekunden speichern (während Video läuft)
if (sessionWatchedTime % 10 === 0) {
    saveProgress(sessionWatchedTime, duration);
}

// Beim Pause/Stop speichern
player.on('pause', function() {
    saveProgress(sessionWatchedTime, duration);
});

// Beim Verlassen der Seite speichern
$(window).on('beforeunload', function() {
    saveProgress(sessionWatchedTime, duration);
});
```

### 3. Fortschritts-Berechnung

```php
// Backend
$progress = ($watched_time / $duration) * 100;

// Frontend
const progress = (watchedTime / duration) * 100;

// Completion Check
if (progress >= $completion_percentage && $user_id) {
    completeWebinar($webinar_id);
}
```

---

## 🧪 Testing

### Test-Szenario 1: Nicht eingeloggt

1. Öffnen Sie `/wissen/webinar/123` ohne Login
2. ✅ Video lädt normal
3. ✅ Login-Hinweis wird angezeigt
4. Schauen Sie 2 Minuten
5. Verlassen Sie die Seite
6. Kehren Sie zurück
7. ✅ Fortschritt aus Cookie geladen (aber nicht in DB)

### Test-Szenario 2: Eingeloggt

1. Loggen Sie sich ein
2. Öffnen Sie `/wissen/webinar/123`
3. ✅ Kein Login-Hinweis
4. ✅ Fortschritt-Balken sichtbar
5. Schauen Sie 3 Minuten
6. ✅ Fortschritt wird alle 10 Sek gespeichert
7. Überprüfen Sie User Meta: `_vw_watched_time_123` = ~180

### Test-Szenario 3: Vorspulen (Anti-Skip)

1. Starten Sie Video
2. Warten Sie 10 Sekunden (10 Sek angesehen)
3. Spulen Sie zu 5:00 vor
4. ✅ Console: "Forward seek detected - no time added"
5. Warten Sie weitere 10 Sekunden
6. ✅ Total: 20 Sek angesehen (NICHT 5:10!)

### Test-Szenario 4: Completion

1. Eingeloggt öffnen
2. Schauen Sie 90% des Videos tatsächlich an
3. ✅ Bei Erreichen von 90%:
   - `completeWebinar()` wird aufgerufen
   - Fortbildungseintrag erstellt
   - Zertifikat verfügbar
   - Seite wird neu geladen
   - ✅ Completion-Banner wird angezeigt

---

## 🐛 Debugging

### Console Logs:

```javascript
// Aktiviert in script.js
'Forward seek detected - no time added'  // Vorspulen erkannt
```

### Browser DevTools:

**Application → Cookies:**
```
vw_webinar_123 = {"watched_time":180,"progress":15.5}
```

**Network → XHR:**
```
vw_track_progress
  webinar_id: 123
  watched_time: 10     // Sekunden dieser Session
  duration: 1800       // Video-Länge
```

### Database:

```sql
-- User Meta
SELECT * FROM wp_usermeta
WHERE meta_key LIKE '_vw_watched_time_%';

-- Post Meta (Duration Cache)
SELECT * FROM wp_postmeta
WHERE meta_key = '_vw_video_duration';
```

---

## 🚨 Bekannte Einschränkungen

### 1. beforeunload ist nicht 100% zuverlässig

**Problem:** Browser können `beforeunload` blocken (Popup-Blocker, Tracking Protection)

**Lösung:** Alle 10 Sekunden automatisches Speichern

### 2. Cookie-Beschränkungen

**Problem:** Cookies können gelöscht werden, nicht über Domains hinweg

**Lösung:** Login empfohlen für verlässliche Speicherung

### 3. Interval-Genauigkeit

**Problem:** JavaScript Intervals sind nicht perfekt (±50ms)

**Lösung:** Akzeptabel für diesen Use Case

---

## 📈 Performance

### Vorher (v1.1.0):

- AJAX bei jedem `timeupdate` Event (~4x pro Sekunde)
- Server-Last: **HOCH**
- DB-Writes: **~240/Min**

### Nachher (v1.2.0):

- AJAX alle 10 Sekunden (wenn Video läuft)
- Server-Last: **NIEDRIG**
- DB-Writes: **~6/Min**

**Reduktion: ~97%** 🎉

---

## ✅ Vorteile von v1.2.0

1. ✅ **Anti-Skip:** Vorspulen wird nicht als Fortschritt gezählt
2. ✅ **Öffentlich:** Webinare für alle verfügbar (Marketing-Vorteil)
3. ✅ **Performance:** 97% weniger Server-Last
4. ✅ **Cookie Support:** Fortschritt auch ohne Login
5. ✅ **Genauigkeit:** Sekundengenau statt positionsbasiert
6. ✅ **UX:** Klarer Login-Hinweis, besseres Feedback

---

## 📞 Support

**Fragen?** Kontaktieren Sie DGPTM Support mit:
- WordPress Version
- Browser + Version
- Console Errors (F12)
- Network Tab Screenshot (F12 → Network → XHR)

---

**Version 1.2.0 ist produktionsbereit!** 🚀

Getestet mit:
- WordPress 6.4+
- PHP 8.0+
- Chrome, Firefox, Safari, Edge
