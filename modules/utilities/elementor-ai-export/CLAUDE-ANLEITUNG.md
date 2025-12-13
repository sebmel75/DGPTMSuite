# Anleitung: Elementor-Seiten mit Claude AI bearbeiten

Diese Anleitung zeigt Ihnen, wie Sie Elementor-Seiten korrekt mit Claude AI bearbeiten.

## Problem: Claude gibt kein gültiges JSON zurück

**Häufiger Fehler**: Claude gibt die Antwort als Markdown-formatiertes JSON zurück oder lässt wichtige Felder weg.

### ❌ Falsch (häufig bei Claude)

Claude antwortet mit:
```
Hier ist die bearbeitete Version:

```json
{
  "structure": [
    { "id": "abc", "settings": { "title": "Neue Überschrift" } }
  ]
}
```
```

**Problem**:
1. JSON ist in Markdown-Code-Block eingebettet (nicht direkt verwendbar)
2. Feld `metadata` fehlt
3. Feld `_elementor_settings` fehlt (Dynamic Visibility geht verloren!)
4. Feld `type` fehlt

### ✅ Richtig

Claude gibt zurück:
```json
{
  "metadata": {
    "page_id": 123,
    "title": "Meine Seite",
    "post_type": "page",
    "post_name": "meine-seite",
    "exported_at": "2024-01-15 10:30:00",
    "elementor_version": "3.18.0",
    "page_settings": {}
  },
  "structure": [
    {
      "id": "abc123",
      "type": "section",
      "level": 0,
      "settings": {
        "heading": "Neue Überschrift"
      },
      "_elementor_settings": {
        "heading": "Alte Überschrift",
        "_element_visibility": "logged_in",
        "background_color": "#ffffff",
        ... alle anderen Original-Settings ...
      },
      "children": []
    }
  ]
}
```

## So weisen Sie Claude richtig an

### Schritt 1: Seite exportieren

Exportieren Sie Ihre Seite als **Markdown** oder **JSON**:
- **Markdown**: Besser lesbar, mit eingebauter Anleitung für Claude
- **JSON**: Direkter, aber schwerer zu lesen

### Schritt 2: Datei an Claude übergeben

Fügen Sie die exportierte Datei in Claude ein mit dieser Anweisung:

```
Ich habe hier eine Elementor-Seite als Export.

WICHTIG: Wenn du Änderungen vornimmst, gib mir das VOLLSTÄNDIGE JSON zurück mit:
1. Dem kompletten "metadata" Objekt (unverändert)
2. Dem kompletten "structure" Array
3. ALLEN Feldern pro Element: id, type, level, widget (falls vorhanden), settings, _elementor_settings, children

Das Feld "_elementor_settings" ist KRITISCH - es enthält Dynamic Visibility und alle
Elementor-Pro-Einstellungen. Dieses Feld darf NIEMALS verändert oder weggelassen werden!

Gib mir nur das pure JSON zurück, OHNE Markdown-Code-Blöcke (```).

Jetzt zu meiner Anfrage:
[IHRE ÄNDERUNG, z.B.: "Ändere die Überschrift im ersten Widget zu 'Willkommen'"]
```

### Schritt 3: Claude-Antwort kopieren

Kopieren Sie **NUR das reine JSON** aus Claudes Antwort:

**Wenn Claude antwortet mit**:
```
Hier ist die bearbeitete Version:

```json
{ ... }
```
```

**Dann kopieren Sie NUR**:
```
{ ... }
```

(Ohne die Markdown-Zeichen ` ``` `)

### Schritt 4: Import

Fügen Sie das JSON in das Import-Feld ein und wählen Sie:
- **Staging-Import** (empfohlen): Testen Sie die Änderungen erst
- **Direkter Import**: Überschreibt sofort die Original-Seite

## Häufige Probleme und Lösungen

### Problem 1: "Fehlende Metadaten"

**Ursache**: Claude hat das `metadata`-Feld weggelassen.

**Lösung**: Sagen Sie Claude explizit:
```
Gib mir das KOMPLETTE JSON zurück, inklusive des "metadata" Objekts am Anfang.
```

### Problem 2: "Element hat keine ID"

**Ursache**: Claude hat IDs weggelassen oder verändert.

**Lösung**:
```
WICHTIG: Ändere NIEMALS die "id" Felder! Behalte alle IDs exakt bei.
```

### Problem 3: Dynamic Visibility geht verloren

**Ursache**: Claude hat `_elementor_settings` weggelassen.

**Lösung**:
```
Das Feld "_elementor_settings" muss VOLLSTÄNDIG erhalten bleiben!
Es enthält Dynamic Visibility und alle Elementor-Einstellungen.
Ändere NUR Werte im "settings" Feld, NIEMALS in "_elementor_settings"!
```

### Problem 4: JSON ist in Markdown-Block

**Ursache**: Claude gibt JSON als Code-Block zurück (```json ... ```).

**Lösung 1** (Anweisung):
```
Gib mir das JSON OHNE Markdown-Code-Blöcke zurück.
Ich brauche nur das pure JSON, das direkt mit { beginnt.
```

**Lösung 2** (Manuell):
Entfernen Sie die Zeilen mit ` ```json ` und ` ``` ` vor dem Einfügen.

## Empfohlene Claude-Prompts

### Für Text-Änderungen
```
Ändere [BESCHREIBUNG] zu "[NEUER TEXT]".

Gib mir das vollständige JSON zurück (mit metadata und structure).
Behalte alle Felder bei, besonders "_elementor_settings".
Kein Markdown, nur pures JSON.
```

### Für mehrere Änderungen
```
Ich brauche folgende Änderungen:
1. [ÄNDERUNG 1]
2. [ÄNDERUNG 2]
3. [ÄNDERUNG 3]

WICHTIG:
- Gib das VOLLSTÄNDIGE JSON zurück (metadata + structure)
- Behalte ALLE Felder bei (id, type, widget, level, settings, _elementor_settings, children)
- Ändere NUR die angeforderten Werte in "settings"
- Das Feld "_elementor_settings" bleibt KOMPLETT unverändert
- Kein Markdown (```), nur pures JSON
```

### Für komplexe Änderungen
```
[IHRE ANFRAGE]

Befolge diese Regeln strikt:
1. Gib das KOMPLETTE JSON zurück (beginnt mit { "metadata": {...}, "structure": [...] })
2. Jedes Element MUSS haben: id, type, level, settings, _elementor_settings
3. NIEMALS ändern: id, type, level, widget, _elementor_settings
4. NUR ändern: Werte in "settings" wie angefordert
5. Kein Markdown-Code-Block, nur pures JSON

Das ist für einen automatischen Import - das JSON muss perfekt sein!
```

## Export-Formate im Vergleich

| Format | Vorteil | Nachteil | Empfehlung |
|--------|---------|----------|------------|
| **Markdown** | Lesbar, mit Anleitung für Claude | Muss als JSON zurückgegeben werden | ✅ Für erste Bearbeitung |
| **JSON** | Direkter Re-Import möglich | Schwerer zu lesen | ✅ Für Re-Import |
| **YAML** | Strukturiert | Wird nicht unterstützt beim Import | ❌ Nicht empfohlen |

## Workflow-Empfehlung

1. **Export als Markdown** → Claude versteht die Struktur gut
2. **Claude bearbeiten** → Mit klaren Anweisungen (siehe oben)
3. **JSON kopieren** → Ohne Markdown-Blöcke
4. **Staging-Import** → Sicher testen
5. **Prüfen** → In Elementor ansehen
6. **Übernehmen** → Auf Original-Seite anwenden

## Tipp: JSON validieren

Wenn Sie unsicher sind, ob Claudes JSON gültig ist:

1. Gehen Sie zu https://jsonlint.com
2. Fügen Sie das JSON ein
3. Klicken Sie "Validate JSON"
4. Fehler werden angezeigt

Häufige JSON-Fehler:
- Fehlende Kommas
- Doppelte Kommas am Ende
- Ungeschlossene Klammern
- Fehlende Anführungszeichen

## Support

Bei Problemen:
1. Prüfen Sie die Fehlermeldung beim Import
2. Validieren Sie das JSON auf jsonlint.com
3. Stellen Sie sicher, dass alle Felder vorhanden sind
4. Nutzen Sie die empfohlenen Claude-Prompts oben

---

**Version**: 1.0.0
**Modul**: DGPTM Elementor AI Export
**Dokumentation**: C:\Users\SebastianMelzer\Desktop\Backup Website\dgptm-plugin-suite\modules\utilities\elementor-ai-export\
