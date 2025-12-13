# Migration: File-Based Metadata System

## Änderungen in Version 3.1.0

Das DGPTM Plugin Suite System wurde auf ein dateibasiertes Metadata-System umgestellt.

### Was wurde geändert?

#### 1. Kategorien-System
- **Neu:** `categories.json` im Root-Verzeichnis
- Kategorien werden zentral definiert mit Namen, Beschreibung, Icon und Farbe
- Kategorien-Zuordnung erfolgt über `category`-Feld in module.json
- Verzeichnisstruktur (`modules/{category}/{module-id}/`) hat KEINE Bedeutung mehr für die Kategorisierung
- Module können in beliebigen Verzeichnissen liegen

#### 2. Flags und Metadaten
- **Vorher:** Flags wurden in `wp_options` als `dgptm_suite_module_metadata` gespeichert
- **Jetzt:** Flags werden direkt in `module.json` gespeichert
- **Neues Feld in module.json:**
  ```json
  {
    "flags": ["production", "important"],
    "comment": "Freitext-Kommentar",
    "test_version_link": "module-id-test"
  }
  ```

#### 3. Verfügbare Flags
Definiert in `categories.json`:
- `testing` - Modul wird getestet (blau)
- `deprecated` - Veraltet (rot)
- `important` - Wichtig (gelb)
- `development` - In Entwicklung (grün)
- `production` - Produktiv (lila)
- `beta` - Beta-Version (cyan)

### Neue Dateien

1. **categories.json** - Zentrale Kategorien- und Flag-Definitionen
2. **MODULE-SCHEMA.md** - Dokumentation des erweiterten module.json Schemas
3. **core/class-module-metadata-file.php** - Neue Metadata-Klasse für dateibasierte Speicherung

### Automatische Migration

Beim ersten Laden nach dem Update wird automatisch eine Migration durchgeführt:

1. System liest alte Metadaten aus `wp_options`
2. Metadaten werden in die entsprechenden `module.json` Dateien geschrieben
3. Flag `dgptm_suite_metadata_migrated` wird gesetzt
4. Migration erfolgt nur einmal

### Für Entwickler

#### Metadaten lesen:
```php
$metadata = DGPTM_Module_Metadata_File::get_instance();

// Flags eines Moduls
$flags = $metadata->get_flags('module-id');

// Kommentar lesen
$comment = $metadata->get_comment('module-id');

// Alle Metadaten
$all = $metadata->get_module_metadata('module-id');
```

#### Metadaten schreiben:
```php
$metadata = DGPTM_Module_Metadata_File::get_instance();

// Flag hinzufügen
$metadata->add_flag('module-id', 'production');

// Flag entfernen
$metadata->remove_flag('module-id', 'testing');

// Kommentar setzen
$metadata->set_comment('module-id', 'Wichtiges Produktiv-Modul');

// Test-Version verknüpfen
$metadata->link_test_version('module-main', 'module-test');
```

#### Module.json erweitern:
```json
{
  "id": "my-module",
  "name": "My Module",
  "category": "utilities",
  "flags": ["production", "important"],
  "comment": "Critical module for XYZ"
}
```

### Backwards Compatibility

- Alte `module.json` ohne neue Felder funktionieren weiterhin
- Alte Metadata-Klasse (`DGPTM_Module_Metadata`) bleibt verfügbar
- System verwendet automatisch `DGPTM_Module_Metadata_File`

### Vorteile

1. **Versionskontrolle:** Metadata ist Teil der Dateien (Git-freundlich)
2. **Portabilität:** Module können mit Metadaten exportiert werden
3. **Keine Datenbank:** Kein Caching-Problem, keine wp_options Bloat
4. **Transparenz:** Alles lesbar in JSON-Dateien
5. **Flexibilität:** Kategorien können zentral definiert/angepasst werden

### Migration prüfen

Nach dem Update kannst du die Migration prüfen:

1. Gehe zu DGPTM Suite Dashboard
2. Überprüfe, ob Flags noch vorhanden sind
3. Öffne einzelne `module.json` Dateien - Flags sollten darin stehen
4. In der Datenbank: `wp_options` Tabelle - `dgptm_suite_metadata_migrated` sollte `true` sein

### Troubleshooting

**Flags fehlen nach Update:**
- Prüfe `debug.log` auf Migration-Meldungen
- Lösche Option `dgptm_suite_metadata_migrated` in Datenbank
- Lade Dashboard neu → Migration startet erneut

**Kategorie "uncategorized":**
- Modul-Verzeichnis hat keine `category` in module.json
- Füge `"category": "utilities"` hinzu (oder passende Kategorie)

**Schreibrechte-Fehler:**
- Stelle sicher, dass `modules/` Verzeichnis beschreibbar ist
- Prüfe Dateirechte für `module.json` Dateien

### Rollback (falls nötig)

Falls Probleme auftreten:
1. Öffne `dgptm-master.php`
2. Ändere alle `DGPTM_Module_Metadata_File` zurück zu `DGPTM_Module_Metadata`
3. Alte Metadaten sind noch in `wp_options` vorhanden
