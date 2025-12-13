# Module.json Schema

Jedes Modul benötigt eine `module.json` Datei mit folgender Struktur:

## Pflichtfelder

- **id** (string): Eindeutige Modul-ID (lowercase, keine Leerzeichen)
- **name** (string): Anzeigename des Moduls
- **description** (string): Kurzbeschreibung
- **version** (string): Versionsnummer (SemVer)
- **author** (string): Autor des Moduls
- **main_file** (string): Haupt-PHP-Datei
- **category** (string): Kategorie-ID (siehe categories.json)

## Optionale Felder

- **dependencies** (array): Abhängigkeiten zu anderen DGPTM-Modulen
- **optional_dependencies** (array): Optionale Modul-Abhängigkeiten
- **wp_dependencies** (object): WordPress-Plugin-Abhängigkeiten
  - **plugins** (array): Array von Plugin-Slugs
- **requires_php** (string): Minimale PHP-Version
- **requires_wp** (string): Minimale WordPress-Version
- **icon** (string): Dashicon-Klasse (Standard: "dashicons-admin-plugins")
- **active** (boolean): Aktivierungsstatus (wird automatisch verwaltet)
- **can_export** (boolean): Kann als Standalone-Plugin exportiert werden (Standard: true)
- **critical** (boolean): Kritisches Modul, kann nicht deaktiviert werden (Standard: false)

## Neue Metadaten-Felder

- **flags** (array): Array von Flag-IDs (siehe categories.json für verfügbare Flags)
  - Beispiel: ["testing", "important"]
- **comment** (string): Freitext-Kommentar zum Modul
- **test_version_link** (string): Modul-ID der Test-Version (für Versionswechsel)

## Beispiel

```json
{
  "id": "herzzentren",
  "name": "DGPTM - Herzzentrum Editor",
  "description": "Heart center management with maps and Elementor widgets",
  "version": "4.0.1",
  "author": "Sebastian Melzer",
  "main_file": "dgptm-herzzentrum-editor.php",
  "dependencies": [],
  "optional_dependencies": [],
  "wp_dependencies": {
    "plugins": ["elementor", "advanced-custom-fields"]
  },
  "requires_php": "7.4",
  "requires_wp": "5.8",
  "category": "business",
  "icon": "dashicons-location",
  "active": false,
  "can_export": true,
  "critical": false,
  "flags": ["production", "important"],
  "comment": "Aktiv im Produktivbetrieb, kritisch für Website",
  "test_version_link": "herzzentren-test"
}
```

## Migration

Bestehende module.json-Dateien ohne die neuen Felder funktionieren weiterhin.
Die neuen Felder sind optional und werden bei Bedarf automatisch hinzugefügt.

Bestehende Flags/Kommentare aus wp_options werden beim ersten Laden automatisch
in die module.json migriert.
