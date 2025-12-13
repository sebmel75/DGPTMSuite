# Fehler-Schutz-System für Module

## Übersicht

Das DGPTM Plugin Suite verfügt über ein intelligentes Fehler-Schutz-System, das verhindert, dass Module mit bekannten Fehlern erneut aktiviert werden können. Dies schützt WordPress vor wiederholten kritischen Fehlern und erzwingt, dass Fehler zuerst behoben werden.

## Funktionsweise

### Automatische Fehler-Erkennung

Wenn ein Modul beim Laden einen kritischen Fehler verursacht:

1. **Safe Loader fängt Fehler ab** (try-catch, shutdown handler)
2. **Modul wird automatisch deaktiviert** (außer bei kritischen Modulen)
3. **Fehler wird gespeichert** in `dgptm_suite_failed_activations` Option
4. **Zeitstempel wird gesetzt** für zeitbasierte Wiederholungsversuche

### Aktivierungsblockierung

**Datei**: `admin/class-plugin-manager.php`
**Methode**: `activate_module()`
**Zeilen**: 275-310

**Ablauf bei Aktivierungsversuch**:

```php
// 1. Prüfe ob Modul bekannten Fehler hat
$failed_activations = $safe_loader->get_failed_activations();

if (isset($failed_activations[$module_id])) {
    $error_age = time() - $error_info['timestamp'];

    // 2. Blockiere wenn Fehler < 1 Stunde alt
    if ($error_age < 3600) {
        // BLOCKIERT - Admin-Notice anzeigen
        return false;
    }

    // 3. Wenn älter: Retry erlaubt, aber Warnung
    if ($error_age >= 3600) {
        // WARNUNG - Erlaube Retry
        // Lösche alten Fehler für frischen Versuch
    }
}

// 4. Normal aktivieren
```

### Zeitbasierte Entsperrung

- **< 1 Stunde**: Aktivierung **BLOCKIERT**
- **≥ 1 Stunde**: Aktivierung **ERLAUBT** mit Warnung
- **Nach Retry**: Alter Fehler wird gelöscht für frischen Versuch

## UI-Visualisierung

### Dashboard-Badges

**Fehler-Badge** (`dashboard.php:355-369`):

```php
<?php if ($has_error): ?>
    <span class="dgptm-status-badge dgptm-status-error">
        <span class="dashicons dashicons-warning"></span>
        <?php echo $error_is_recent
            ? 'FEHLER (blockiert)'
            : 'FEHLER (alt)'; ?>
    </span>
<?php endif; ?>
```

**Badge-Typen**:
- 🔴 **FEHLER (blockiert)** - Rot, Fehler < 1h, Aktivierung blockiert
- 🟠 **FEHLER (alt)** - Orange, Fehler ≥ 1h, Retry erlaubt

**Tooltip zeigt**:
- Fehlerzeitpunkt (human-readable: "vor 15 Minuten")
- Fehlermeldung (verkürzt)

### Button-Blockierung

**Aktivieren-Button** (`dashboard.php:385`):

```php
<button class="dgptm-toggle-module"
        <?php echo ($error_is_recent)
            ? 'disabled title="Modul hat kürzlich Fehler..."'
            : ''; ?>>
    Activate
</button>
```

**Button-Zustände**:
- ✅ **Aktivierbar** - Kein Fehler oder Fehler > 1h alt
- ⛔ **Deaktiviert** - Fehler < 1h, Tooltip erklärt Grund
- ⚠️ **Warnung** - Fehler > 1h, Button aktiv aber Admin-Notice

### Fehler-Löschen-Button

**Nur bei fehlerhaften Modulen sichtbar** (`dashboard.php:412-417`):

```php
<?php if ($has_error): ?>
    <button class="dgptm-clear-error" data-module-id="...">
        <span class="dashicons dashicons-dismiss"></span>
        Fehler löschen
    </button>
<?php endif; ?>
```

**Funktionalität**:
- AJAX-Call zu `ajax_clear_module_error`
- Löscht Fehler-Eintrag aus Datenbank
- Erlaubt sofortigen Retry (ohne 1h Wartezeit)
- Nach Erfolg: Page-Reload

## Admin-Benachrichtigungen

### Bei Aktivierungsversuch (Fehler < 1h)

```
❌ Modul "module-id" kann nicht aktiviert werden, da es beim letzten
   Versuch einen kritischen Fehler verursacht hat.

   Fehler: Call to undefined function xyz()

   Bitte beheben Sie den Fehler zuerst oder warten Sie 1 Stunde
   für einen erneuten Versuch.
```

**Typ**: Error (rot)
**Blockiert**: Ja

### Bei Aktivierungsversuch (Fehler ≥ 1h)

```
⚠️ Warnung: Modul "module-id" hatte beim letzten Versuch einen Fehler.
   Bitte prüfen Sie die Logs nach erfolgreicher Aktivierung.
```

**Typ**: Warning (gelb)
**Blockiert**: Nein, aber gewarnt

## Fehler-Speicherformat

**Option**: `dgptm_suite_failed_activations`

**Struktur**:
```php
[
    'module-id' => [
        'timestamp' => 1700000000,
        'error' => [
            'message' => 'Call to undefined function...',
            'file' => '/path/to/file.php',
            'line' => 123,
            'type' => 'FatalError'
        ],
        'auto_deactivated' => true,
        'can_retry' => false  // Wird auf true gesetzt nach 1h
    ],
    'critical-module_critical' => [
        // Kritische Module haben _critical Suffix
        'is_critical_module' => true,
        'protection_triggered' => true,
        'severity' => 'CRITICAL'
    ]
]
```

## AJAX-Handler

### Clear Module Error

**Handler**: `class-plugin-manager.php:1873-1898`
**Action**: `dgptm_clear_module_error`

**Request**:
```javascript
{
    action: 'dgptm_clear_module_error',
    module_id: 'problematic-module',
    nonce: '...'
}
```

**Response (Erfolg)**:
```json
{
    "success": true,
    "data": {
        "message": "Fehler-Eintrag für Modul \"...\" wurde gelöscht...",
        "module_id": "problematic-module"
    }
}
```

**JavaScript**: `admin.js:396-429`

## Szenarien

### Szenario 1: Modul mit Syntax-Fehler aktivieren

**Ausgangssituation**:
- Modul `buggy-module` hat PHP-Syntaxfehler in Zeile 42
- Admin versucht Aktivierung

**Ablauf**:
1. Admin klickt "Activate"
2. Safe Loader versucht zu laden
3. **PHP Parse Error** wird abgefangen
4. Modul wird deaktiviert
5. Fehler wird in DB gespeichert (Timestamp: jetzt)
6. Page-Reload: Modul zeigt **"FEHLER (blockiert)"** Badge
7. "Activate" Button ist **disabled**
8. Tooltip: "Modul hat kürzlich einen Fehler verursacht..."

**Admin-Optionen**:
- ⏰ **Warten** (1 Stunde) → Retry möglich
- 🔧 **Fehler beheben** → "Fehler löschen" Button → Retry sofort
- 📋 **Logs prüfen** → System Logs für Details

### Szenario 2: Fehler beheben und neu aktivieren

**Ausgangssituation**:
- Modul hatte Fehler vor 30 Minuten (< 1h)
- Admin hat Fehler im Code behoben
- Admin will erneut aktivieren

**Ablauf**:
1. Admin klickt **"Fehler löschen"** Button
2. Bestätigungs-Dialog: "Fehler-Eintrag löschen?"
3. AJAX-Call zu `dgptm_clear_module_error`
4. Fehler-Eintrag wird aus DB entfernt
5. Success-Alert: "Fehler-Eintrag wurde gelöscht..."
6. Page-Reload
7. **Badge verschwunden**, "Activate" Button **aktiv**
8. Admin klickt "Activate"
9. Modul lädt erfolgreich (Fehler war behoben)
10. Kein neuer Fehler-Eintrag

### Szenario 3: Alte Fehler (> 1 Stunde)

**Ausgangssituation**:
- Modul hatte Fehler vor 2 Stunden
- Admin hat vergessen zu beheben

**Ablauf**:
1. Dashboard zeigt **"FEHLER (alt)"** Badge (orange)
2. "Activate" Button ist **AKTIV** (nicht disabled)
3. Admin klickt "Activate"
4. **Warnung-Notice** erscheint: "Modul hatte beim letzten Versuch..."
5. Alter Fehler-Eintrag wird gelöscht
6. Aktivierung wird versucht
7. **Fehler tritt erneut auf** (nicht behoben!)
8. Neuer Fehler-Eintrag wird erstellt
9. Cycle beginnt von vorne

## Integration mit anderen Systemen

### Mit Safe Loader

**Safe Loader** speichert Fehler:
```php
// In class-safe-loader.php
private function auto_deactivate_module($module_id, $error_info) {
    $failed_activations[$module_id] = [
        'timestamp' => time(),
        'error' => $error_info,
        'auto_deactivated' => true,
        'can_retry' => false
    ];
    update_option('dgptm_suite_failed_activations', $failed_activations);
}
```

**Plugin Manager** liest und prüft:
```php
// In class-plugin-manager.php
$failed_activations = $safe_loader->get_failed_activations();
if (isset($failed_activations[$module_id])) {
    // Blockiere Aktivierung
}
```

### Mit Critical Module Protection

**Kritische Module**:
- Werden **NIEMALS** automatisch deaktiviert
- Fehler wird trotzdem gespeichert (mit `_critical` Suffix)
- Aktivierung kann **NICHT** blockiert werden (sind immer aktiv)
- Admin erhält **E-Mail** statt Aktivierungssperre

### Mit System Logs

Alle Fehler-Operationen werden geloggt:

```
[ERROR] Safe Loader: Modul 'buggy-module' verursachte Fehler: Parse error...
[WARNING] Aktivierung blockiert: Modul 'buggy-module' hat bekannten Fehler (Alter: 1800s)
[INFO] Aktivierung erlaubt: Fehler von 'buggy-module' ist älter als 1 Stunde - Retry erlaubt
[INFO] Fehler-Eintrag für Modul 'buggy-module' wurde manuell gelöscht
```

## Konfiguration

### Zeitlimit anpassen

Aktuell: **1 Stunde (3600 Sekunden)**

**Ändern in `class-plugin-manager.php:285`**:
```php
// Aktuell
if ($error_age < 3600) { ... }

// Auf 30 Minuten ändern
if ($error_age < 1800) { ... }

// Auf 24 Stunden ändern
if ($error_age < 86400) { ... }
```

### Aktivierungssperre deaktivieren

**Nicht empfohlen!** Aber möglich:

```php
// In class-plugin-manager.php:275
// Kommentiere die gesamte Fehlerprüfung aus
/*
if (isset($failed_activations[$module_id])) {
    // ...
}
*/
```

## Best Practices

### Für Administratoren

1. **Logs prüfen**: Bei Fehler-Badge immer System Logs prüfen
2. **Fehler beheben**: Nicht einfach warten, sondern Code korrigieren
3. **Fehler löschen**: Nach Behebung "Fehler löschen" nutzen
4. **Nicht forcieren**: Wenn Modul blockiert ist, gibt es einen Grund

### Für Entwickler

1. **Syntax prüfen**: `php -l` vor Deployment
2. **Testing**: Module vor Live-Aktivierung testen
3. **Error Handling**: Eigene try-catch Blöcke für kritische Bereiche
4. **Logging**: Eigene Fehler loggen für besseres Debugging

## Debugging

### Fehler-Einträge prüfen

**Via PHP**:
```php
$safe_loader = DGPTM_Safe_Loader::get_instance();
$failed = $safe_loader->get_failed_activations();
var_dump($failed);
```

**Via wp-cli**:
```bash
wp option get dgptm_suite_failed_activations --format=json
```

### Alle Fehler zurücksetzen

**Manuell in DB**:
```sql
DELETE FROM wp_options
WHERE option_name = 'dgptm_suite_failed_activations';
```

**Via Code**:
```php
$safe_loader = DGPTM_Safe_Loader::get_instance();
$safe_loader->clear_all_errors();
```

### Fehler-Badge erscheint nicht

**Mögliche Ursachen**:
1. Cache-Problem: STRG+F5 im Browser
2. Fehler-Eintrag fehlt: Option prüfen
3. Modul in falscher Kategorie
4. JavaScript-Fehler: Browser Console prüfen

## Sicherheit

### Schutzmaßnahmen

1. **Nonce-Prüfung**: Alle AJAX-Calls nonce-verified
2. **Capability-Check**: `manage_options` erforderlich
3. **Input-Sanitization**: `sanitize_text_field()` auf module_id
4. **XSS-Schutz**: `esc_attr()`, `esc_html()` im Output
5. **SQL-Injection**: Keine direkten DB-Queries, nur WP Options API

### Was NICHT möglich ist

- ❌ User ohne Admin-Rechte können Fehler NICHT löschen
- ❌ Fehler-Einträge können NICHT gefälscht werden
- ❌ Zeitstempel können NICHT manipuliert werden (serverseitig)
- ❌ Kritische Module können NICHT blockiert werden

## Performance

### Auswirkungen

- **Minimal**: Nur eine Option-Abfrage pro Aktivierungsversuch
- **Gecached**: `get_option()` wird von WordPress gecached
- **Keine DB-Queries**: Nur WP Options API
- **UI**: Badges werden server-side gerendert (kein AJAX)

### Optimierung

Option ist bereits optimiert:
- Nur Module mit Fehlern werden gespeichert
- Alte Einträge werden automatisch bei Retry gelöscht
- Keine automatische Cleanup-Routine nötig

## Versionierung

- **Eingeführt**: v1.1.1 (2025-11-29)
- **Status**: Produktiv, vollständig integriert
- **Breaking Changes**: Keine

## Support

Bei Problemen:

1. **System Logs prüfen** - DGPTM Suite → System Logs
2. **Browser Console** - JavaScript-Fehler?
3. **Option prüfen** - `dgptm_suite_failed_activations`
4. **Cache leeren** - STRG+F5 im Browser
5. **Fehler manuell löschen** - Via Code oder DB

## FAQ

**Q: Kann ich die 1-Stunden-Sperre umgehen?**
A: Ja, mit dem "Fehler löschen" Button. Aber beheben Sie den Fehler zuerst!

**Q: Warum ist mein kritisches Modul nicht blockiert?**
A: Kritische Module können nie blockiert werden. Sie erhalten stattdessen eine E-Mail.

**Q: Fehler-Badge bleibt nach Behebung?**
A: Klicken Sie "Fehler löschen" oder warten Sie 1 Stunde für Auto-Retry.

**Q: Kann ich alle Fehler auf einmal löschen?**
A: Nicht via UI, aber via Code: `$safe_loader->clear_all_errors()`

**Q: Wird der Fehler beim erfolgreichen Retry gelöscht?**
A: Ja, automatisch beim nächsten erfolgreichen Laden.

## Code-Referenz

**Hauptimplementierung**:
- Aktivierungssperre: `admin/class-plugin-manager.php:275-310`
- UI-Badges: `admin/views/dashboard.php:222-228, 355-369`
- Button-Blockierung: `admin/views/dashboard.php:385`
- Fehler-Löschen-Button: `admin/views/dashboard.php:412-417`
- AJAX-Handler: `admin/class-plugin-manager.php:1873-1898`
- JavaScript: `admin/assets/js/admin.js:396-429`

**Abhängigkeiten**:
- Safe Loader (`class-safe-loader.php`)
- Failed Activations Option (`dgptm_suite_failed_activations`)
- WordPress Options API
- WordPress AJAX API
