# Upgrade-Guide: Version 3.7 → 4.0.0

## Übersicht

Dieser Guide hilft dir beim Upgrade von den alten separaten Plugins zur neuen vereinigten Version 4.0.0.

## ⚠️ Wichtige Hinweise vor dem Upgrade

1. **Backup erstellen**: Erstelle ein vollständiges Backup deiner Website (Dateien + Datenbank)
2. **Testumgebung**: Teste das Upgrade zuerst in einer Staging-Umgebung
3. **Abhängigkeiten prüfen**: Stelle sicher, dass Elementor und Elementor Pro aktuell sind
4. **Wartungsmodus**: Aktiviere den Wartungsmodus während des Upgrades

## 🔄 Upgrade-Prozess

### Schritt 1: Backup erstellen

```bash
# Datenbank-Backup
mysqldump -u username -p database_name > backup_$(date +%Y%m%d).sql

# Dateien-Backup
tar -czf plugins_backup_$(date +%Y%m%d).tar.gz wp-content/plugins/
```

### Schritt 2: Alte Plugins deaktivieren

1. Im WordPress-Admin zu **Plugins** navigieren
2. Folgende Plugins deaktivieren (aber NICHT löschen):
   - `DGPTM - Herzzentrum Editor` (Version 3.7)
   - `GRT Elementor Herzzentren Map Single`

**Wichtig**: Nicht löschen, nur deaktivieren! Die Einstellungen bleiben erhalten.

### Schritt 3: Neues Plugin installieren

1. ZIP-Datei `dgptm-herzzentren-unified.zip` hochladen
2. Plugin aktivieren
3. Elementor-Cache leeren (siehe unten)

### Schritt 4: Elementor-Seiten aktualisieren

Die Widgets haben neue Namen und müssen in Elementor aktualisiert werden:

#### Automatische Migration (empfohlen)

Das Plugin erkennt alte Widgets automatisch und zeigt eine Migrations-Notice im Admin-Bereich.

#### Manuelle Migration

Falls die automatische Migration nicht funktioniert:

1. **Multi-Map Widget**:
   - Altes Widget: "GRT Elementor Herzzentren Map"
   - Neues Widget: "Herzzentren Karte"
   - Öffne jede Seite mit dem alten Widget in Elementor
   - Lösche das alte Widget
   - Füge das neue Widget "Herzzentren Karte" hinzu
   - Speichern und Veröffentlichen

2. **Single-Map Widget**:
   - Altes Widget: "GRT Elementor Herzzentren Map Single"
   - Neues Widget: "Herzzentrum Einzelkarte"
   - Öffne jede Seite mit dem alten Widget in Elementor
   - Lösche das alte Widget
   - Füge das neue Widget "Herzzentrum Einzelkarte" hinzu
   - Koordinaten aus alten Feldern übertragen
   - Speichern und Veröffentlichen

### Schritt 5: Cache leeren

```
1. Elementor Cache leeren:
   - Elementor → Tools → Regenerate Files & Data
   - "Regenerate Files" klicken

2. WordPress Object Cache leeren (falls verwendet)

3. Browser-Cache leeren

4. CDN-Cache leeren (falls verwendet)
```

### Schritt 6: Testing

Teste folgende Funktionen auf allen relevanten Seiten:

- [ ] Multi-Map zeigt alle Herzzentren korrekt an
- [ ] Single-Map zeigt einzelne Standorte korrekt an
- [ ] Popups öffnen und schließen sich korrekt
- [ ] Marker sind sichtbar und klickbar
- [ ] Scroll-Zoom funktioniert (Click-to-Enable)
- [ ] Mobile Ansicht ist optimiert
- [ ] Editor-Funktionen arbeiten korrekt
- [ ] Berechtigungen funktionieren wie erwartet

### Schritt 7: Alte Plugins löschen

**Erst nach erfolgreichem Testing!**

1. Zu **Plugins** navigieren
2. Folgende Plugins löschen:
   - `DGPTM - Herzzentrum Editor` (Version 3.7)
   - `GRT Elementor Herzzentren Map Single`

## 🔍 Widget-Mapping

### Multi-Map Widget

**Alt:**
```
Widget-Name: GRT Elementor Herzzentren Map
Namespace: GRT_Elementor_Herzzentren_Map
Klasse: GRT_Elementor_Herz_Map
Handle: grt-elementor-widgets-herzzentren-map
```

**Neu:**
```
Widget-Name: Herzzentren Karte
Namespace: Global (DGPTM_Herzzentren_Map_Widget)
Klasse: DGPTM_Herzzentren_Map_Widget
Handle: dgptm-herzzentren-map
```

**Neue Einstellungen:**
- Kartenhöhe (Slider)
- Anfangs-Zoom (Slider)
- Popup bei Seitenaufruf (Toggle)
- Popup-Hintergrundfarbe
- Popup-Textfarbe

### Single-Map Widget

**Alt:**
```
Widget-Name: GRT Elementor Herzzentren Map Single
Namespace: GRT_Elementor_Herzzentren_Map_Single
Klasse: GRT_Elementor_Herz_Map_Single
Handle: grt-elementor-widgets-herzzentren-map-single
```

**Neu:**
```
Widget-Name: Herzzentrum Einzelkarte
Namespace: Global (DGPTM_Herzzentrum_Single_Map_Widget)
Klasse: DGPTM_Herzzentrum_Single_Map_Widget
Handle: dgptm-herzzentrum-single-map
```

**Neue Einstellungen:**
- Marker anzeigen (Toggle)
- Marker-Titel (Text, dynamisch)
- Marker-Beschreibung (Textarea, dynamisch)
- Kartenhöhe (Slider)
- Zoom-Level (Slider)
- Scroll-Zoom deaktivieren (Toggle)
- Popup-Farben

## 📊 Datenbank-Änderungen

Das neue Plugin nutzt die gleichen Post Types und Meta-Felder:
- Post Type: `herzzentrum`
- Meta-Felder: Alle bleiben erhalten

**Keine Datenbank-Migration erforderlich!**

## 🎨 CSS-Änderungen

### Alte Klassen
```css
.hrz__map-canvas
.hrz__map-canvas-single
.hrz__map-tooltip
.hrz__map-tooltip-name
.hrz__map-tooltip-address
.hrz__map-tooltip-link
```

### Neue Klassen (zusätzlich)
```css
.dgptm-herzzentren-map-wrapper
.dgptm-herzzentrum-single-map-wrapper
.dgptm-map-canvas
.dgptm-map-canvas-single
.dgptm-map-popup
.dgptm-map-popup__title
.dgptm-map-popup__address
.dgptm-map-popup__link
.dgptm-custom-popup
```

**Alte Klassen bleiben aus Kompatibilitätsgründen erhalten.**

Falls du Custom CSS verwendest, solltest du es auf die neuen Klassen anpassen:

```css
/* Alt */
.hrz__map-tooltip-name { ... }

/* Neu (empfohlen) */
.dgptm-map-popup__title { ... }
```

## ⚙️ JavaScript-Änderungen

### Alte API (nicht mehr verfügbar)
```javascript
// Keine öffentliche API in alten Versionen
```

### Neue API
```javascript
// Maps neu initialisieren
window.dgptmMaps.reinit();

// Map zerstören
window.dgptmMaps.destroy('map-id');

// Zugriff auf Map-Instanz
const instance = window.dgptmMaps.instances['map-id'];
```

## 🐛 Troubleshooting

### Problem: Widgets werden nicht angezeigt

**Lösung:**
1. Elementor Cache leeren
2. Plugin deaktivieren und neu aktivieren
3. WordPress Permalinks neu speichern

### Problem: Karten werden nicht geladen

**Lösung:**
1. Browser-Console öffnen (F12)
2. Leaflet-Fehler prüfen
3. Stelle sicher, dass Leaflet.js geladen wird
4. Prüfe auf JavaScript-Konflikte mit anderen Plugins

### Problem: Marker werden nicht angezeigt

**Lösung:**
1. Prüfe ob Koordinaten korrekt gespeichert sind
2. Prüfe Post-Status (muss "publish" sein)
3. Prüfe Browser-Console auf Fehler
4. Stelle sicher, dass Marker-Images erreichbar sind

### Problem: Alte Widgets bleiben sichtbar

**Lösung:**
1. Alte Plugins müssen deaktiviert sein
2. Elementor Cache leeren
3. Browser Cache leeren
4. WordPress Object Cache leeren

### Problem: Styling sieht anders aus

**Lösung:**
1. Neue CSS-Datei wird geladen? (Prüfe Netzwerk-Tab)
2. Cache-Plugins deaktivieren
3. Hard-Refresh im Browser (Ctrl+Shift+R)
4. Custom CSS anpassen (siehe CSS-Änderungen oben)

## 📞 Support

Bei Problemen während des Upgrades:

1. **Fehler dokumentieren**: Screenshots, Browser-Console-Logs
2. **Systeminfo sammeln**: WordPress-Version, PHP-Version, Elementor-Version
3. **Kontakt**: Sebastian Melzer (DGPTM)

## ✅ Post-Upgrade Checklist

Nach erfolgreichem Upgrade:

- [ ] Alle Seiten mit Maps getestet
- [ ] Mobile Ansichten geprüft
- [ ] Editor-Funktionen getestet
- [ ] Berechtigungen überprüft
- [ ] Performance gemessen
- [ ] Alte Plugins gelöscht
- [ ] Backup archiviert
- [ ] Team informiert
- [ ] Dokumentation aktualisiert

## 🎉 Fertig!

Glückwunsch! Du hast erfolgreich auf Version 4.0.0 upgegraded und profitierst nun von:

- Vereinigter Plugin-Architektur
- Optimierter Map-Darstellung
- Erhöhter Sicherheit
- Besserer Performance
- Modernem Design

Bei Fragen oder Feedback melde dich bei Sebastian Melzer.
