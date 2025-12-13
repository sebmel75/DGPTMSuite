# Automatisches Module-Cleanup System

## Übersicht

Das DGPTM Plugin Suite verfügt über ein automatisches Cleanup-System, das Module aus der Datenbank entfernt, wenn diese nicht mehr im Filesystem vorhanden sind. Dies verhindert Fehler und hält die Datenbank sauber.

## Funktionsweise

### Wann läuft das Cleanup?

Das Cleanup läuft **automatisch** bei jedem Laden der Module:
- Bei jedem Admin-Seitenaufruf
- Bei jedem Frontend-Seitenaufruf
- Nach Plugin-Updates
- Nach manueller Modul-Löschung via FTP/Filesystem

### Was wird bereinigt?

Das System entfernt:
1. **Aktivierungsstatus** in `dgptm_suite_settings['active_modules']`
2. **Einträge von gelöschten Modulen** aus der Datenbank

### Wie funktioniert es?

**Datei**: `core/class-module-loader.php`
**Methode**: `cleanup_missing_modules()`
**Zeilen**: 541-572

**Algorithmus**:
```
1. Scanne alle Module im Filesystem (modules/**/module.json)
2. Lade alle Module aus der Datenbank (dgptm_suite_settings)
3. Vergleiche: Welche DB-Module fehlen im Filesystem?
4. Entferne fehlende Module aus active_modules Array
5. Speichere bereinigte Datenbank
6. Zeige Admin-Notice mit entfernten Modulen
```

**Code**:
```php
private function cleanup_missing_modules($active_modules) {
    $available_module_ids = array_keys($this->module_paths);
    $database_module_ids = array_keys($active_modules);

    // Finde Module die in der DB sind, aber nicht im Filesystem
    $missing_modules = array_diff($database_module_ids, $available_module_ids);

    if (empty($missing_modules)) {
        return ['cleaned' => false];
    }

    // Entferne fehlende Module
    foreach ($missing_modules as $module_id) {
        unset($active_modules[$module_id]);
    }

    return [
        'cleaned' => true,
        'removed_modules' => array_values($missing_modules),
        'active_modules' => $active_modules
    ];
}
```

## Admin-Benachrichtigung

### Notice-Anzeige

Wenn Module bereinigt wurden, erscheint eine **gelbe Warning-Notice** im WordPress-Admin:

**Einzelnes Modul**:
```
⚠️ DGPTM Suite: Das Modul my-module wurde aus der Datenbank entfernt,
   da es nicht mehr im Filesystem vorhanden ist.
```

**Mehrere Module**:
```
⚠️ DGPTM Suite: Die folgenden Module wurden aus der Datenbank entfernt,
   da sie nicht mehr im Filesystem vorhanden sind: module-1, module-2, module-3
```

### Notice-Eigenschaften

- **Typ**: Warning (gelb)
- **Dismissible**: Ja (kann weggeklickt werden)
- **Dauer**: 24 Stunden (dann automatisch gelöscht)
- **Speicherung**: WordPress Transient `dgptm_cleanup_notice`

## Logging

Alle Cleanup-Operationen werden in die System Logs geschrieben:

**Log-Beispiele**:
```
[WARNING] Module Loader: CLEANUP - Folgende Module existieren nicht mehr im Filesystem: old-module-1, old-module-2
[INFO] Module Loader: CLEANUP - Entferne Modul 'old-module-1' aus der Datenbank
[INFO] Module Loader: CLEANUP - Entferne Modul 'old-module-2' aus der Datenbank
[INFO] Module Loader: CLEANUP - 2 Module aus der Datenbank entfernt
```

**Zugriff**: `DGPTM Suite → System Logs` (Filter: Level "Warning" oder "Info")

## Szenarien

### Szenario 1: Modul via FTP gelöscht

**Ausgangssituation**:
- Modul `test-module` ist in Datenbank als aktiv markiert
- Admin löscht `modules/utilities/test-module/` via FTP

**Was passiert**:
1. Nächster Seitenaufruf triggert Module Loader
2. Cleanup erkennt: `test-module` in DB, aber nicht im Filesystem
3. `test-module` wird aus `active_modules` entfernt
4. Datenbank wird aktualisiert
5. Admin-Notice erscheint: "Das Modul test-module wurde aus der Datenbank entfernt..."
6. Log-Einträge werden erstellt

**Ergebnis**: Datenbank ist sauber, keine Fehler beim Laden

### Szenario 2: Mehrere Module umbenannt

**Ausgangssituation**:
- Module `old-name-1`, `old-name-2` in DB aktiv
- Admin benennt Module um zu `new-name-1`, `new-name-2`
- module.json ID bleibt aber alt

**Was passiert**:
1. Module Loader scannt und findet `new-name-1`, `new-name-2`
2. Cleanup findet `old-name-1`, `old-name-2` in DB, aber nicht im Scan
3. Alte Namen werden aus DB entfernt
4. Admin-Notice zeigt beide Module an
5. Admin muss neue Module manuell aktivieren

**Ergebnis**: Alte Einträge bereinigt, neue Module inaktiv

### Szenario 3: Plugin-Update mit Modul-Entfernung

**Ausgangssituation**:
- Plugin-Update entfernt veraltetes Modul `deprecated-module`

**Was passiert**:
1. Nach Update: Module Loader lädt
2. Cleanup erkennt fehlendes Modul
3. Automatische Bereinigung aus DB
4. Admin-Notice informiert über Entfernung
5. Keine Fehler beim Laden

**Ergebnis**: Sauberer Update-Prozess, transparente Kommunikation

## Integration mit anderen Systemen

### Zusammenspiel mit Critical Module Protection

Cleanup respektiert **NICHT** kritische Module:
- Wenn ein kritisches Modul fehlt, wird es trotzdem aus DB entfernt
- **ABER**: Bei nächstem Laden würde Auto-Reaktivierung einen Fehler loggen
- Empfehlung: Kritische Module niemals löschen!

### Zusammenspiel mit Safe Loader

- Cleanup läuft **VOR** dem eigentlichen Laden der Module
- Module die nicht existieren, werden gar nicht erst geladen
- Verhindert "Module not found" Fehler im Safe Loader

### Zusammenspiel mit Dependency Manager

- Bereinigte Module werden aus Dependency-Checks entfernt
- Andere Module die von gelöschten Modulen abhängen, zeigen korrekte Fehlermeldung
- Keine "ghost dependencies"

## Performance

### Auswirkungen

- **Minimal**: Cleanup läuft nur wenn Module-Array != Filesystem
- **array_diff()** ist O(n) - sehr schnell
- Keine Dateioperationen außer dem normalen Module-Scan
- Datenbank-Update nur wenn tatsächlich bereinigt wurde

### Optimierung

Das System ist bereits optimiert:
```php
// Früher Abbruch wenn nichts zu tun ist
if (empty($missing_modules)) {
    return ['cleaned' => false];
}
```

## Debugging

### Cleanup-Operationen verfolgen

**1. System Logs aktivieren**:
```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

**2. Filter auf Cleanup**:
- DGPTM Suite → System Logs
- Filter: "CLEANUP" im Suchfeld
- Level: Warning, Info

**3. Transient prüfen**:
```php
// WordPress Admin → Optionen → Transienten
// Suche: dgptm_cleanup_notice
$notice = get_transient('dgptm_cleanup_notice');
var_dump($notice);
```

### Cleanup manuell triggern

Cleanup läuft automatisch, aber kann getestet werden:

**Test-Szenario**:
1. Modul in DB als aktiv markieren (wp_options → dgptm_suite_settings)
2. Modul-Ordner temporär umbenennen
3. WordPress-Admin aufrufen
4. Prüfen: Admin-Notice erscheint?
5. Prüfen: System Logs zeigen Cleanup?
6. Prüfen: Modul ist aus dgptm_suite_settings entfernt?

## Datenbank-Struktur

### Betroffene Options

**Option**: `dgptm_suite_settings`
```json
{
  "active_modules": {
    "existing-module": true,
    // "deleted-module": true  <- wird entfernt
  }
}
```

**Transient**: `dgptm_cleanup_notice`
```php
[
  'type' => 'warning',
  'message' => 'DGPTM Suite: Das Modul ... wurde entfernt...',
  'dismissible' => true
]
```

**Gültigkeit**: 24 Stunden (DAY_IN_SECONDS)

## Sicherheit

### Schutzmaßnahmen

1. **Keine Datei-Löschung**: System löscht NIE Dateien
2. **Nur DB-Cleanup**: Entfernt nur Datenbank-Einträge
3. **Logging**: Alle Operationen werden geloggt
4. **Benachrichtigung**: Admin wird immer informiert
5. **Reversibel**: Module können manuell wieder aktiviert werden

### Was NICHT passiert

- ❌ Keine Modul-Dateien werden gelöscht
- ❌ Keine module.json wird verändert
- ❌ Keine Metadaten werden bereinigt
- ❌ Keine Modul-Einstellungen werden gelöscht (nur Aktivierungsstatus)

## Best Practices

### Für Administratoren

1. **Backup vor Löschung**: Immer Backup vor manueller Modul-Löschung
2. **Notice beachten**: Cleanup-Notices nicht ignorieren
3. **Logs prüfen**: Nach großen Änderungen System Logs prüfen
4. **Module archivieren**: Statt löschen, Module deaktivieren

### Für Entwickler

1. **Modul-IDs stabil halten**: IDs nicht ändern (verursacht Cleanup)
2. **Migration bei Umbenennung**: Migrations-Script für ID-Änderungen
3. **Dokumentation**: Entfernte Module in CHANGELOG dokumentieren
4. **Testing**: Cleanup-Verhalten bei Plugin-Updates testen

## Versionierung

- **Eingeführt**: v1.1.0 (2025-11-29)
- **Status**: Produktiv, automatisch aktiv
- **Breaking Changes**: Keine - abwärtskompatibel

## FAQ

**Q: Kann ich das Cleanup deaktivieren?**
A: Nein, das Cleanup ist essentiell für Datenbankintegrität und immer aktiv.

**Q: Was passiert wenn ich ein Modul versehentlich lösche?**
A: Das Modul wird aus der DB entfernt. Stellen Sie die Dateien wieder her und aktivieren Sie es neu.

**Q: Werden Modul-Einstellungen gelöscht?**
A: Nein, nur der Aktivierungsstatus wird entfernt. Modul-spezifische Optionen bleiben erhalten.

**Q: Wie oft läuft das Cleanup?**
A: Bei jedem Module-Loader-Aufruf (jeder Seitenaufruf), aber nur wenn tatsächlich Module fehlen.

**Q: Kann das Cleanup kritische Module löschen?**
A: Ja, aus der DB wird der Eintrag entfernt. Aber kritische Module sollten NIEMALS gelöscht werden!

**Q: Woher weiß ich welche Module bereinigt wurden?**
A: Admin-Notice im Dashboard + System Logs (Level: Warning/Info)

## Support

Bei Problemen mit dem Cleanup-System:

1. **System Logs prüfen** - Filter auf "CLEANUP"
2. **wp_options prüfen** - `dgptm_suite_settings` Option
3. **Transient prüfen** - `dgptm_cleanup_notice`
4. **Module-Scan prüfen** - Sind module.json Dateien korrekt?

## Code-Referenz

**Haupt-Implementierung**:
- Datei: `core/class-module-loader.php`
- Cleanup-Logik: Zeilen 541-572
- Notice-System: Zeilen 574-617
- Integration: Zeilen 163-175

**Abhängigkeiten**:
- WordPress Transient API
- WordPress Admin Notices
- DGPTM Logger System
- Module Paths Array
