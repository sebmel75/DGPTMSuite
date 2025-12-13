# Debug: Modul-Deaktivierung funktioniert nicht

## Sofort-Diagnose

### 1. Browser-Console prüfen (F12)

Öffnen Sie das DGPTM Dashboard und drücken Sie F12:

1. **Console-Tab öffnen**
2. **Versuchen Sie ein Modul zu deaktivieren**
3. **Suchen Sie nach:**
   - ❌ JavaScript-Fehler (rot)
   - ⚠️ AJAX-Fehler
   - ℹ️ Console-Logs wie "DGPTM: Toggle module..."

### 2. Button-Status prüfen

**Im Dashboard:**
1. Rechtsklick auf einen "Deactivate"-Button
2. "Element untersuchen" (Inspect)
3. **Prüfen Sie:**
   ```html
   <button class="button dgptm-toggle-module"
           data-module-id="..."
           data-active="1"
           disabled>  ← Ist "disabled" vorhanden?
   ```

### 3. Welche Module sind betroffen?

**Alle Module?** → JavaScript-Problem
**Nur bestimmte?** → Dependency-Problem

---

## Häufigste Probleme & Lösungen

### Problem 1: Alle Buttons sind disabled

**Ursache:** Dependency-Chain ist falsch berechnet

**Lösung:**
```php
// In admin/class-plugin-manager.php Zeile 283-288
// Temporär auskommentieren um zu testen:

/*
$can_deactivate = $dependency_manager->can_deactivate_module($module_id);
if (!$can_deactivate['can_deactivate']) {
    $this->add_admin_notice($can_deactivate['message'], 'error');
    return false;
}
*/
```

### Problem 2: JavaScript lädt nicht

**Prüfen:**
```javascript
// In Browser-Console eingeben:
typeof dgptmSuite
// Erwartete Ausgabe: "object"

// Wenn "undefined":
```

**Lösung:** Assets neu laden
1. WordPress Admin → Einstellungen → Permalinks → Speichern
2. Browser-Cache leeren (Strg+Shift+R)

### Problem 3: AJAX-Nonce ist abgelaufen

**Symptom:** "AJAX error: 403" in Console

**Lösung:** Seite neu laden (F5)

### Problem 4: Module haben zirkuläre Dependencies

**Symptom:** Buttons zeigen Tooltip "Required by other modules"

**Lösung:** Dependency-Graph prüfen

---

## Schnelltest-Script

Fügen Sie dies temporär in `admin/views/dashboard.php` nach Zeile 200 ein:

```php
<?php
// DEBUG: Zeige warum Button disabled ist
$debug_info = [];
if ($is_critical) $debug_info[] = 'CRITICAL';
if (!$dep_check['status']) $debug_info[] = 'MISSING DEPS';
if (!$can_deactivate['can_deactivate']) $debug_info[] = 'REQUIRED BY: ' . $can_deactivate['message'];

if (!empty($debug_info)):
?>
    <small style="display:block; color: red;">
        DEBUG: <?php echo implode(' | ', $debug_info); ?>
    </small>
<?php endif; ?>
```

Dies zeigt bei jedem Modul, warum der Button disabled ist.

---

## Vollständige Diagnose

### Schritt 1: PHP-Logging aktivieren

In `admin/class-plugin-manager.php` Zeile 262 (Funktion `deactivate_module`):

```php
private function deactivate_module($module_id) {
    // HINZUFÜGEN:
    error_log("DEBUG: Versuche $module_id zu deaktivieren");

    // Prüfe ob Modul als kritisch markiert ist
    $module_loader = dgptm_suite()->get_module_loader();
    $all_modules = $module_loader->get_available_modules();

    // HINZUFÜGEN:
    error_log("DEBUG: Modul gefunden: " . (isset($all_modules[$module_id]) ? 'JA' : 'NEIN'));

    if (isset($all_modules[$module_id])) {
        $config = $all_modules[$module_id]['config'];

        // HINZUFÜGEN:
        error_log("DEBUG: Ist kritisch? " . (!empty($config['critical']) ? 'JA' : 'NEIN'));

        if (!empty($config['critical'])) {
            // ... Rest des Codes
```

### Schritt 2: Debug-Log prüfen

Nach Deaktivierungs-Versuch:
```
Pfad: wp-content/debug.log
```

Suchen nach:
```
DEBUG: Versuche [module-id] zu deaktivieren
```

---

## Notfall-Deaktivierung (manuell)

Falls Dashboard nicht funktioniert:

### Via WordPress Admin:

1. **Tools → WP-CLI oder phpMyAdmin**
2. **Option ändern:**
   ```sql
   SELECT * FROM wp_options WHERE option_name = 'dgptm_suite_settings';
   ```
3. **In `option_value` JSON-Feld:**
   ```json
   {
     "active_modules": {
       "modul-id-hier": false
     }
   }
   ```

### Via FTP/File Manager:

**Temporär ein Modul "ausstecken":**
```
modules/kategorie/modul-name/
  → Umbenennen zu: modul-name.DISABLED
```

---

## Kontakt

Wenn nichts hilft, senden Sie:
1. Browser-Console-Log (Screenshot)
2. wp-content/debug.log (letzten 50 Zeilen)
3. Liste der Module die nicht deaktiviert werden können
