# Vimeo Stream Manager - Changelog v3.1.0

## Mobile Fullscreen Optimierung

### Neue Features

**Externer Fullscreen-Button für Hauptstream**
- ✅ Vollbild-Button wird UNTER dem Hauptstream angezeigt (nicht über dem Video)
- ✅ Nur auf Mobile Devices sichtbar (max-width: 768px)
- ✅ Nutzt native Browser Fullscreen API
- ✅ Fallback auf manuellen Fullscreen wenn API nicht verfügbar
- ✅ Keine Überlagerung der Vimeo-Player-Controls

### Funktionsweise

**Desktop:**
- Keine Änderungen
- Click auf Nebenstream → wird zum Hauptstream (wie bisher)
- Vimeo-Controls bleiben vollständig verfügbar

**Mobile:**
1. **Nebenstreams:**
   - Click auf Nebenstream → Ton wird aktiviert (wie bisher)
   - Click auf anderen Nebenstream → Ton wechselt

2. **Hauptstream:**
   - Vimeo-Player mit allen Standard-Controls
   - **NEU:** "Vollbild"-Button unter dem Video
   - Click auf Button → Hauptstream geht in Browser-Fullscreen
   - Im Fullscreen: Close-Button (×) oben rechts zum Beenden

### Technische Details

**Fullscreen Modi:**

1. **Native Browser Fullscreen (Standard):**
   ```javascript
   element.requestFullscreen()
   ```
   - Nutzt Fullscreen API
   - Funktioniert auf den meisten modernen Browsern
   - Vimeo-Controls bleiben voll funktionsfähig

2. **Manueller Fallback (wenn API nicht verfügbar):**
   ```javascript
   element.classList.add('vsm-manual-fullscreen')
   ```
   - CSS-basierter Fullscreen
   - Fixed positioning mit 100vw x 100vh
   - Funktioniert auch auf älteren Geräten

**CSS-Klassen:**

- `.vsm-fullscreen-controls` - Container für Fullscreen-Button (nur Mobile)
- `.vsm-fullscreen-button` - Der Vollbild-Button
- `.vsm-fullscreen-close` - Close-Button im Fullscreen-Modus
- `.vsm-manual-fullscreen` - Manueller Fullscreen-Modus
- `.vsm-fullscreen-active` - Container-Status während Fullscreen

**Event-Handling:**

```javascript
// Fullscreen aktivieren
$('.vsm-fullscreen-button').click() → toggleFullscreen()

// Fullscreen beenden
$('.vsm-fullscreen-close').click() → exitFullscreen()

// Browser Fullscreen Change
document.addEventListener('fullscreenchange', ...)
```

### Vorteile der neuen Lösung

✅ **Keine Konflikte mit Vimeo-Controls**
- Button ist außerhalb des iframes
- Vimeo-Player bleibt unberührt

✅ **Native Browser-Features**
- Nutzt Standard Fullscreen API
- Bessere Performance
- Bessere Kompatibilität

✅ **Benutzerfreundlich**
- Klarer, großer Button
- Eindeutige Funktion
- Einfache Bedienung

✅ **Responsive**
- Automatische Anpassung an Geräte
- Landscape/Portrait Support
- Skaliert mit Bildschirmgröße

### Breaking Changes

Keine! Die bestehende Funktionalität bleibt vollständig erhalten:
- Desktop-Verhalten unverändert
- Stream-Switching funktioniert wie bisher
- Sound-Control funktioniert wie bisher
- Alle Einstellungen kompatibel

### Browser-Kompatibilität

**Fullscreen API:**
- ✅ Chrome/Edge (Desktop + Mobile)
- ✅ Firefox (Desktop + Mobile)
- ✅ Safari (Desktop + iOS)
- ✅ Opera (Desktop + Mobile)

**Fallback (manueller Fullscreen):**
- ✅ Alle Browser (auch ältere)
- ✅ iOS < 12
- ✅ Android < 5

### Verwendung

**Für End-User:**

1. Webinar/Stream-Seite öffnen
2. Auf Mobile: Scroll zum Hauptstream
3. Click auf "Vollbild"-Button unter dem Video
4. Video läuft im Fullscreen
5. Close-Button (×) oder Zurück-Taste zum Beenden

**Für Entwickler:**

Keine Code-Änderungen notwendig! Das Modul funktioniert automatisch:

```php
// Shortcode wie bisher
[vimeo_streams]

// Mit Parametern
[vimeo_streams tag="Tag 1" buttons="off"]
```

### CSS-Anpassungen (optional)

Button-Farben anpassen:

```css
.vsm-fullscreen-button {
    background: linear-gradient(135deg, #YOUR_COLOR 0%, #YOUR_COLOR_DARK 100%);
}
```

Button-Größe anpassen:

```css
.vsm-fullscreen-button {
    padding: 15px 30px;
    font-size: 18px;
}
```

### Bekannte Limitierungen

1. **iOS Safari Fullscreen:**
   - iOS erlaubt nur Fullscreen für Video-Elemente
   - Lösung: Manueller Fullscreen wird automatisch verwendet

2. **Android Browser < 5:**
   - Kein Fullscreen API Support
   - Lösung: Manueller Fullscreen wird automatisch verwendet

3. **Eingebettete iframes:**
   - Fullscreen funktioniert nur wenn Parent-Seite es erlaubt
   - Lösung: `allowfullscreen` Attribut setzen (bereits implementiert)

### Testing

**Getestet auf:**
- ✅ iPhone 12/13/14 (iOS 15+)
- ✅ Samsung Galaxy S20/S21 (Android 11+)
- ✅ iPad Pro (iOS 15+)
- ✅ Chrome Desktop (Windows/Mac/Linux)
- ✅ Firefox Desktop (Windows/Mac/Linux)
- ✅ Safari Desktop (Mac)

### Migration von v3.0.x

Keine Migration notwendig! Einfach updaten:

1. Neue Dateien überschreiben
2. Browser-Cache leeren (wegen neuer CSS/JS Versionen)
3. Fertig!

### Support

Bei Problemen:
1. Browser-Console öffnen (F12)
2. Nach Fehlern suchen
3. Debug-Mode aktivieren: `?vsm_debug=1` an URL anhängen
4. Screenshots/Logs an Support senden

### Nächste Schritte

Mögliche zukünftige Erweiterungen:
- Picture-in-Picture Mode
- Auto-Rotate zu Landscape im Fullscreen
- Vollbild-Button auch für Desktop (optional)
- Keyboard-Shortcuts (ESC zum Beenden)
