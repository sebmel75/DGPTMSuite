# Vimeo Batch Import - Anleitung

Mit dem Batch-Import können Sie alle Videos aus einem Vimeo-Ordner automatisch als Webinare importieren.

## Voraussetzungen

1. **Vimeo Personal Access Token**
   - Gehen Sie zu: https://developer.vimeo.com/api/authentication
   - Erstellen Sie einen neuen Token
   - Erforderliche Berechtigung: **Private** (zum Lesen Ihrer Videos)

2. **Vimeo-Ordner**
   - Videos müssen in einem Vimeo-Ordner (Project) organisiert sein
   - Der Ordner muss in Ihrem Vimeo-Account sein

## Schritt-für-Schritt Anleitung

### 1. Navigation

WordPress Admin → Webinare → **Batch Import**

### 2. API-Verbindung einrichten

1. Vimeo Personal Access Token eingeben
2. "Verbindung testen" klicken
3. Bei Erfolg: ✓ Verbindung erfolgreich! Benutzer: [Ihr Name]

### 3. Ordner auswählen

1. "Ordner laden" klicken
2. Warten Sie, bis alle Ordner geladen sind
3. Ordner aus der Liste auswählen
   - Anzeige: `Ordnername (X Videos)`

### 4. Import-Einstellungen

**Kategorie** (optional):
- Freitext-Feld für Kategorisierung
- Wird als Meta-Feld gespeichert
- Beispiel: "Kardiologie", "Allgemeinmedizin"

**Fortbildungspunkte**:
- ☑ **Automatisch berechnen**: 1 Punkt pro 60 Minuten Video-Länge
- ☐ **Standard-Punkte**: Feste Anzahl für alle Videos

### 5. Import starten

1. "Batch-Import starten" klicken
2. Bestätigen Sie den Import
3. Warten Sie, bis der Import abgeschlossen ist
4. Ergebnis wird angezeigt

## Was wird importiert?

Für jedes Video wird ein Webinar-Post erstellt mit:

- ✓ **Titel** - Video-Name von Vimeo
- ✓ **Beschreibung** - Video-Beschreibung
- ✓ **Vimeo-ID** - Eindeutige Video-ID
- ✓ **Vimeo-URL** - Link zum Video
- ✓ **Dauer** - Video-Länge in Sekunden
- ✓ **Vorschaubild** - Thumbnail von Vimeo
- ✓ **Fortbildungspunkte** - Automatisch oder manuell
- ✓ **Kategorie** - Falls angegeben
- ✓ **Status** - Als **Entwurf** erstellt

## Wichtig: Duplikate

- Videos mit gleicher Vimeo-ID werden **übersprungen**
- Bereits importierte Videos bleiben unverändert
- Im Ergebnis werden übersprungene Videos aufgelistet

## Nach dem Import

1. Gehen Sie zu: **Webinare → Alle Webinare**
2. Filter: **Entwürfe**
3. Überprüfen Sie die importierten Webinare
4. Bearbeiten Sie bei Bedarf:
   - ACF-Felder (wenn vorhanden)
   - Zusätzliche Beschreibungen
   - Kategorien
5. Status ändern: **Entwurf → Veröffentlicht**

## Import-Ergebnis

Das Ergebnis zeigt:

✓ **Erfolgreich importiert** - Grüne Liste
- Neu erstellte Webinare

⊗ **Übersprungen** - Gelbe Liste
- Bereits vorhandene Videos (gleiche Vimeo-ID)

✗ **Fehler** - Rote Liste
- Videos, die nicht importiert werden konnten
- Mit Fehlermeldung

## Beispiel-Workflow

### Szenario: Import von 50 Kardiologie-Videos

1. Vimeo-Ordner "Kardiologie Fortbildung 2024" erstellen
2. 50 Videos zum Ordner hinzufügen
3. WordPress → Batch Import
4. Token eingeben → Verbindung testen
5. Ordner "Kardiologie Fortbildung 2024 (50 Videos)" auswählen
6. Kategorie: "Kardiologie"
7. ☑ Automatisch berechnen (Fortbildungspunkte)
8. Import starten
9. Ergebnis:
   - ✓ 48 erfolgreich importiert
   - ⊗ 2 übersprungen (bereits vorhanden)
   - ✗ 0 Fehler
10. Webinare überprüfen und veröffentlichen

## Fortbildungspunkte-Berechnung

**Automatisch** (empfohlen):
- Video 30 Min → 1 Punkt (aufgerundet)
- Video 60 Min → 1 Punkt
- Video 90 Min → 2 Punkte
- Video 120 Min → 2 Punkte
- Video 150 Min → 3 Punkte

**Manuell**:
- Alle Videos erhalten die gleiche Anzahl Punkte
- Standard: 1 Punkt

## Fehlerbehandlung

**Problem: "Kein API Token angegeben"**
- Token-Feld ist leer
- Lösung: Token eingeben

**Problem: "Verbindung fehlgeschlagen"**
- Token ungültig
- Lösung: Neuen Token erstellen

**Problem: "Keine Ordner gefunden"**
- Ihr Vimeo-Account hat keine Ordner
- Lösung: In Vimeo Ordner erstellen

**Problem: "Video-ID nicht erkennbar"**
- Vimeo-Video hat ungültiges Format
- Lösung: Video in Vimeo prüfen

**Problem: Import bleibt hängen**
- Server-Timeout
- Lösung: Kleinere Ordner verwenden (max. 50 Videos)

## API-Limits

Vimeo API hat Limits:
- **1000 Requests pro Stunde** (Personal Access Token)
- Bei großen Ordnern (>100 Videos) kann es dauern
- Import pausiert automatisch bei Limit-Überschreitung

## Sicherheit

- ✓ API Token wird verschlüsselt in Datenbank gespeichert
- ✓ Alle Importe als Entwurf (kein Auto-Publish)
- ✓ Duplikat-Prüfung verhindert doppelte Importe
- ✓ Fehler-Handling: Bei Fehler bleiben bereits importierte Videos erhalten
- ✓ Nur Admins mit `manage_options` können importieren

## Technische Details

**Import-Prozess**:
1. Vimeo API: `/me/projects/{folder_id}/videos` abrufen
2. Für jedes Video:
   - Duplikat-Check (Vimeo-ID)
   - Post erstellen (vimeo_webinar)
   - Meta-Felder setzen
   - Thumbnail herunterladen
3. Ergebnis zurückgeben

**Datenbank-Felder**:
- `vimeo_id` - Video-ID (Duplikat-Check)
- `vimeo_url` - Video-URL
- `duration` - Länge in Sekunden
- `kategorie` - Kategorie (optional)
- `fortbildungspunkte` - Anzahl Punkte

## Support

Bei Problemen:
1. Browser-Konsole prüfen (F12)
2. WordPress Debug-Log prüfen
3. Vimeo API Status prüfen: https://vimeostatus.com/
