# Vimeo Stream Manager Multi v3.0.4

## 🎬 Multi-Stream-Layout mit automatischem Audio-Management

Das Plugin zeigt mehrere Vimeo-Streams gleichzeitig auf einer Seite und unterstützt sowohl normale Videos als auch Events/Livestreams:
- **Kleine Nebenstreams oben** (automatisch stumm geschaltet)
- **Großer Hauptstream unten** (mit Ton)
- **Klick-to-Switch**: Klick auf einen Nebenstream macht ihn zum Hauptstream
- **Event/Livestream Support**: Volle Unterstützung für Vimeo Events

## ✨ Hauptfeatures

### 1. **Multi-Stream-Ansicht**
- Bis zu 5 Streams gleichzeitig
- Automatische Anordnung: Kleine Streams oben, Hauptstream unten
- Dynamisches Grid-Layout (2-4 Spalten konfigurierbar)
- Nicht verwendete Stream-Slots werden automatisch ausgeblendet

### 2. **Intelligentes Audio-Management**
- Nur der Hauptstream hat Ton
- Alle Nebenstreams sind automatisch stumm
- Beim Stream-Wechsel wird der Ton automatisch umgeschaltet
- Visuelle Indikatoren: 🔇 für stumme Streams, 🔊 für Stream mit Ton

### 3. **Freie Tag-Definition**
- Definieren Sie eigene Tage/Events (nicht auf Wochentage beschränkt)
- Beispiele: "Tag 1", "Vormittag", "Session A", "15.03.2024"
- Jedem Tag können bis zu 5 Streams zugeordnet werden
- Tag-Auswahl-Buttons unter den Streams

### 4. **Flexibles Layout**
- Einstellbare Stream-Höhen (oben/unten separat)
- Grid-Spalten konfigurierbar (2-4 Spalten)
- Responsive Design für mobile Geräte
- Optional: Banner über den Streams

### 5. **Passwortschutz**
- Optional aktivierbar
- Cookie-basierte Authentifizierung (7 Tage gültig)
- Elegantes Passwort-Formular

## 📦 Installation

1. Plugin-Ordner nach `/wp-content/plugins/` hochladen
2. Plugin im WordPress-Backend aktivieren
3. Unter "Vimeo Streams" im Admin-Menü konfigurieren

## 🚀 Verwendung

### Basic Shortcode
```
[vimeo_streams]
```

### Shortcode-Parameter

#### Buttons ausblenden
```
[vimeo_streams buttons="off"]
```
Versteckt die Tag-Auswahl-Buttons

#### Festen Tag anzeigen
```
[vimeo_streams tag="Tag 1"]
```
Zeigt direkt einen bestimmten Tag ohne Auswahlmöglichkeit

#### Grid-Spalten überschreiben
```
[vimeo_streams columns="3"]
```
Setzt die Anzahl der Spalten für die oberen Streams (2-4)

#### Passwortschutz per Shortcode
```
[vimeo_streams password="geheim123"]
```
Setzt ein Passwort für diese spezifische Instanz (überschreibt globale Einstellung)

#### Referer-Limitierung (Iframe-only)
```
[vimeo_streams allowed_referers="2025.fokusperfusion.de"]
```
- Erlaubt nur Iframe-Einbindung von angegebenen Domains
- Mehrere Domains komma-getrennt: `"domain1.de,domain2.de"`
- Blockiert automatisch Direktzugriff wenn gesetzt

### Kombinierte Parameter
```
[vimeo_streams tag="Session A" buttons="off" columns="4"]
[vimeo_streams password="event2024" allowed_referers="event.dgptm.de"]
[vimeo_streams password="dgptm" allowed_referers="2025.fokusperfusion.de,event.dgptm.de" tag="Tag 1" buttons="off"]
```

## 🔒 Zugriffskontrolle

### Passwortschutz

Es gibt zwei Möglichkeiten, Passwortschutz zu implementieren:

1. **Global** (in den Plugin-Einstellungen):
   - Gilt für alle Shortcode-Instanzen
   - Kann per Shortcode überschrieben werden

2. **Per Shortcode**:
   ```
   [vimeo_streams password="mein_passwort"]
   ```
   - Gilt nur für diese spezifische Instanz
   - Überschreibt globale Einstellung
   - Session-basiert (7 Tage gültig nach Eingabe)

### Referer-Limitierung (Iframe-Only Modus)

Verwenden Sie `allowed_referers` um Streams nur als Iframe von bestimmten Domains zuzulassen:

```
[vimeo_streams allowed_referers="2025.fokusperfusion.de"]
```

**Funktionsweise:**
- Wenn `allowed_referers` gesetzt ist, wird Direktzugriff blockiert
- Nur Iframe-Einbindung von den angegebenen Domains ist erlaubt
- Mehrere Domains komma-getrennt angeben

**Beispiel für mehrere Domains:**
```
[vimeo_streams allowed_referers="event.dgptm.de,2025.fokusperfusion.de,partner-site.com"]
```

**Use Case:**
- Streams nur auf bestimmten Event-Seiten einbinden
- Verhindern von direktem Zugriff auf Stream-URLs
- Content-Protection für bezahlte Events

## ⚙️ Konfiguration

### Stream-Verwaltung

1. **Neuen Tag anlegen**
   - Geben Sie einen beliebigen Namen ein (z.B. "Tag 1", "Montag", "Event A")
   - Klicken Sie auf "Tag hinzufügen"

2. **Streams zuordnen**
   - Klicken Sie auf "Bearbeiten" beim gewünschten Tag
   - Fügen Sie bis zu 5 Vimeo Video IDs ein
   - Optional: Beschriftungen für jeden Stream
   - Die ersten Streams werden oben klein angezeigt
   - Der letzte konfigurierte Stream wird zum Hauptstream

3. **Stream-Reihenfolge**
   - Stream 1-4: Kleine Nebenstreams oben (stumm)
   - Stream 5: Großer Hauptstream unten (mit Ton)
   - Leere Slots werden automatisch übersprungen

### Layout-Einstellungen

- **Grid-Spalten**: 2-4 Spalten für obere Streams
- **Max. obere Streams**: Begrenzt die Anzahl der kleinen Streams (1-4)
- **Stream-Höhen**: Separate Höhen für obere und untere Streams
- **Auto-Switch Audio**: Ton automatisch beim Stream-Wechsel umschalten

### Banner-Konfiguration

- Optional einblendbarer Banner über den Streams
- HTML-Unterstützung für formatierten Text
- Ideal für Ankündigungen oder Informationen

## 🎯 Performance-Tipps

- **Optimale Stream-Anzahl**: 3-4 Streams gleichzeitig
- **Empfohlene Höhen**: 
  - Obere Streams: 200-300px
  - Hauptstream: 400-600px
- **Bandbreite**: Beachten Sie die Internetverbindung Ihrer Besucher
- **Mobile Optimierung**: Auf Mobilgeräten wird automatisch auf 1 Spalte reduziert

## 🔧 Technische Details

### Vimeo Player API
- Nutzt die offizielle Vimeo Player JavaScript API
- Autoplay mit Mute-Policy-Kompatibilität
- Background-Mode für stumme Streams
- Loop-Funktion für kontinuierliche Wiedergabe

### Browser-Kompatibilität
- Chrome, Firefox, Safari, Edge (aktuelle Versionen)
- Mobile Browser voll unterstützt
- Autoplay-Policies werden beachtet

### JavaScript-Events
Das Plugin triggert folgende Events:
- `vsm:day-changed` - Wenn ein anderer Tag ausgewählt wird
- `vsm:stream-switched` - Wenn Streams getauscht werden
- `vsm:player-ready` - Wenn ein Player initialisiert wurde

## 📝 Vimeo Video IDs finden

### Normale Videos
1. Öffnen Sie das Video auf Vimeo
2. Die URL sieht so aus: `https://vimeo.com/123456789`
3. Die Zahlen am Ende (123456789) sind die Video ID
4. **Eingabe im Plugin:** `123456789`

### Events/Livestreams
1. Öffnen Sie das Event auf Vimeo
2. Die URL sieht so aus: `https://vimeo.com/event/12345`
3. Die Event-ID ist die Zahl nach /event/
4. **Eingabe im Plugin:** `event/12345`

⚠️ **Wichtige Unterschiede bei Events/Livestreams:**
- Events können nicht im Background-Modus laufen (keine automatische Stummschaltung)
- Obere Event-Streams starten ohne Ton (User muss klicken)
- Der Hauptstream (unten) startet automatisch mit Ton
- Events haben eigene Kontrollelemente von Vimeo

## 🐛 Fehlerbehebung

### Debug-Modus aktivieren
Fügen Sie `?vsm_debug=1` an die URL an, um detaillierte Konsolen-Ausgaben zu erhalten:
```
https://ihre-seite.de/streams/?vsm_debug=1
```

### Livestreams funktionieren nicht
- Prüfen Sie die Event-ID: `event/12345` (mit "event/" Prefix!)
- Stellen Sie sicher, dass das Event aktiv/öffentlich ist
- Mobile: User muss ggf. einmal tippen zum Starten
- Browser-Konsole auf Fehler prüfen

### Mobile-Probleme
- **Loading hängt:** Cache leeren, Seite neu laden
- **Kein Autoplay:** Normal auf Mobile, User muss tippen
- **Kein Ton:** Mobile startet immer stumm (Browser-Policy)
- **iOS:** Stellen Sie sicher, dass "Stummschalter" aus ist

### Streams werden nicht angezeigt
- Prüfen Sie die Vimeo Video IDs
- Stellen Sie sicher, dass die Videos öffentlich oder entsprechend freigegeben sind
- Prüfen Sie die Browser-Konsole auf Fehler

### Autoplay funktioniert nicht
- Browser blockieren Autoplay mit Ton
- Die stummen Nebenstreams sollten automatisch starten
- Der Hauptstream startet möglicherweise erst nach User-Interaktion

### Layout-Probleme
- Cache leeren (Browser und WordPress)
- Prüfen Sie Theme-Konflikte
- Custom CSS in den Plugin-Einstellungen nutzen

## 📄 Changelog

### Version 3.0.4
- **NEU:** Desktop: Hauptstream mit 60px Padding unten für freie Vimeo-Steuerleiste
- **NEU:** Mobile: Alle Streams gleichberechtigt (einheitliche Höhe 200px)
- **NEU:** Mobile: Alle Streams standardmäßig stumm
- **NEU:** Mobile: Klick auf Stream aktiviert Ton (andere werden stumm)
- **NEU:** Mobile Landscape: Automatisches Vollbild für Stream mit aktivem Ton
- **VERBESSERT:** Desktop: Original Click-to-Switch Funktionalität beibehalten
- **OPTIMIERT:** Getrennte Event-Handler für Desktop und Mobile
- **FIX:** Korrekte Dateistruktur mit assets/ Ordner

### Version 3.0.3
- **NEU:** Passwortschutz per Shortcode: `password="geheim"`
- **NEU:** Referer-Limitierung per Shortcode: `allowed_referers="domain.de"`
- **NEU:** Automatische Blockierung von Direktzugriff bei gesetzten allowed_referers
- **VERBESSERT:** Session-basierte Passwort-Verwaltung pro Instanz
- **VERBESSERT:** Bessere Fehlerseiten für Referer-Blockierung

### Version 3.0.2
- **OPTIMIERT:** Mobile-Unterstützung massiv verbessert
- **FIX:** Loading-Problem auf Mobilgeräten behoben
- **OPTIMIERT:** Livestream-Einbindung für Events verbessert
- **NEU:** Besseres Error-Handling mit Timeouts
- **NEU:** Mobile-Hinweis "Tippen zum Abspielen"
- **NEU:** Debug-Mode mit `?vsm_debug=1`
- **VERBESSERT:** Touch-Feedback auf Mobilgeräten
- **FIX:** iOS Safari Kompatibilität
- **OPTIMIERT:** Landscape-Modus auf Mobile

### Version 3.0.1
- **NEU:** Volle Unterstützung für Vimeo Events/Livestreams
- **VERBESSERT:** Unterschiedliche Behandlung von Events und normalen Videos
- **VERBESSERT:** Events verwenden spezielle Embed-URLs
- **FIX:** Audio-Management für Events optimiert
- **UPDATE:** Dokumentation für Event-Verwendung erweitert

### Version 3.0.0
- Komplett neues Multi-Stream-Layout
- Alle Streams gleichzeitig sichtbar
- Automatisches Audio-Management
- **Unterstützung für Vimeo Events/Livestreams und normale Videos**
- Freie Tag-Definition (nicht mehr auf Wochentage beschränkt)
- Click-to-Switch Funktionalität
- Verbesserte Performance
- Grid-Layout mit konfigurierbaren Spalten

## 🤝 Support

Bei Fragen oder Problemen wenden Sie sich an:
- DGPTM Support
- https://dgptm.de

## 📜 Lizenz

GPL v2 or later

---

**Entwickelt von DGPTM** - Deutsche Gesellschaft für Perfusionstechnologie und Extrakorporale Zirkulation
