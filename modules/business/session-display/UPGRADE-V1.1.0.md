# Session Display v1.1.0 - Upgrade Zusammenfassung

## Neue Features

### 1. Vollbild-Modus
- **Automatischer Vollbild**: Alle Displays starten automatisch im Vollbild-Modus
- **F11-Hinweis**: Benutzer kann mit F11 manuell Vollbild beenden/starten
- **Responsive**: Optimiert für alle Bildschirmgrößen

### 2. Debug-Zeitsteuerung
- **Test-Zeit einstellen**: Im Backend alle Displays auf eine bestimmte Zeit setzen
- **Test-Datum einstellen**: Drei Modi verfügbar:
  - **Kein Debug-Datum**: Verwendet heutiges Datum
  - **Veranstaltungstag**: Simuliert Tag 1-5 der Veranstaltung (berechnet aus Event-Datum)
  - **Benutzerdefiniert**: Festes Datum wählen
- **Kombiniert**: Zeit + Datum für vollständige Kontrolle
- **Nützlich für**: Testen von Sessions zu beliebigen Zeiten und Tagen
- **Einfaches Toggle**: Ein/Ausschalten der Debug-Zeitsteuerung

### 3. Optimierte Lesbarkeit
- **Größere Schriften**: Titel, Sprecher und Zeiten deutlich vergrößert
- **Besserer Kontrast**: Optimierte Farbgebung für Lesbarkeit aus Entfernung
- **Klare Hierarchie**: Visuelle Unterscheidung zwischen aktuell/nächster Session

### 4. Hintergrundbilder-Galerie
- **Galerie-Support**: Mehrere Hintergrundbilder rotieren
- **Shortcode-Parameter**: `background_gallery="123"` (Galerie-ID)
- **Einzelbild**: `background_image="https://..."` weiterhin möglich
- **Automatische Rotation**: Konfigurierbare Intervalle

### 5. Venues ohne Raum ausblenden
- **Automatisches Filtern**: Venues ohne zugeordneten Raum werden nicht angezeigt
- **Saubere Darstellung**: Nur relevante Sessions werden geladen
- **Backend-Option**: Toggle zum Anzeigen/Verstecken

## Änderungen im Detail

### Admin-Einstellungen erweitert
```php
// Neue Optionen:
- dgptm_session_display_fullscreen_auto (bool)
- dgptm_session_display_debug_enabled (bool)
- dgptm_session_display_debug_time (string, HH:MM)
- dgptm_session_display_debug_date_mode (string: 'off'|'event_day'|'custom')
- dgptm_session_display_debug_date_custom (string, YYYY-MM-DD)
- dgptm_session_display_debug_event_day (int, 1-5)
- dgptm_session_display_hide_no_room (bool)
- dgptm_session_display_bg_gallery_interval (int, ms)
```

### Shortcode-Parameter erweitert
```php
[session_display
    room="Raum 1"
    type="both"
    background_gallery="123"  // NEU
    fullscreen="true"          // NEU
]
```

### CSS-Verbesserungen
- Schriftgrößen verdoppelt für bessere Lesbarkeit
- Vollbild-Optimierungen (100vh, kein Scrollen)
- Hintergrundbilder mit Overlay für besseren Kontrast

### JavaScript-Features
- Automatischer Vollbild-Modus beim Laden
- Debug-Zeit-Override für alle Zeit-Berechnungen
- Debug-Datum-Anzeige (z.B. "09:00 | Mo, 24.03 (DEBUG)")
- Hintergrundbilder-Rotation mit Fade-Effekt
- Keyboard-Shortcuts (F11 für Vollbild-Toggle)

## Upgrade-Schritte

1. **Backup**: Vor dem Update Backup erstellen
2. **Update**: Dateien ersetzen
3. **Einstellungen**: Backend → Session Display → Neue Optionen konfigurieren
4. **Testen**: Debug-Zeit nutzen um verschiedene Szenarien zu testen
5. **Produktion**: Debug-Zeit deaktivieren für Live-Betrieb

## Kompatibilität

- **Rückwärtskompatibel**: Alte Shortcodes funktionieren weiter
- **Neue Features optional**: Alle neuen Features sind opt-in
- **WordPress**: Getestet mit WP 5.8+
- **PHP**: Kompatibel mit PHP 7.4+

## Bekannte Einschränkungen

- Vollbild-Modus erfordert Benutzer-Interaktion (Browser-Sicherheit)
- Galerie-Rotation funktioniert nur mit WordPress-Galerien
- Debug-Zeit beeinflusst ALLE Displays gleichzeitig

## Support

Bei Problemen:
1. System Logs prüfen (DGPTM Suite → System Logs)
2. Browser Console öffnen (F12)
3. Debug-Modus aktivieren für detaillierte Logs
