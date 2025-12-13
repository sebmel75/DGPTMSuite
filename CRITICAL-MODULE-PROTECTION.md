# Critical Module Protection System

## Übersicht

Das DGPTM Plugin Suite verfügt über ein mehrschichtiges Schutzsystem für kritische Module. Dieses System stellt sicher, dass:

1. **Kritische Module niemals versehentlich deaktiviert werden**
2. **WordPress bei Fehlern in kritischen Modulen stabil bleibt**
3. **Administratoren sofort über Probleme informiert werden**
4. **Massen-Deaktivierungen verhindert werden**

## Was sind kritische Module?

Ein Modul gilt als **kritisch**, wenn **EINE** der folgenden Bedingungen erfüllt ist:

1. **Config-basiert**: Das Feld `"critical": true` in `module.json`
2. **Flag-basiert**: Das Flag `"important"` ist gesetzt

Beispiel kritische Module:
- `crm-abruf` - Zoho CRM Integration
- `menu-control` - Menüsteuerung
- `otp-login` - Authentifizierung

## Schutzschichten

### Layer 1: UI-Schutz (Dashboard)

**Datei**: `admin/views/dashboard.php`
**Zeilen**: 317-322, 358

**Funktionalität**:
- Zeigt "CRITICAL" Badge in rot an
- Deaktivieren-Button ist disabled
- Tooltip erklärt warum Deaktivierung nicht möglich ist

**Code**:
```php
<?php if ($is_critical): ?>
    <span class="dgptm-status-badge dgptm-status-critical">
        <span class="dashicons dashicons-shield"></span>
        <?php _e('CRITICAL', 'dgptm-suite'); ?>
    </span>
<?php endif; ?>

<!-- Button ist deaktiviert -->
<button class="dgptm-toggle-module"
        <?php echo $is_critical ? 'disabled' : ''; ?>>
```

### Layer 2: AJAX-Handler-Schutz

**Datei**: `admin/class-plugin-manager.php`
**Methode**: `deactivate_module()`
**Zeilen**: 331-353

**Funktionalität**:
- Prüft vor Deaktivierung ob Modul kritisch ist
- Verwendet `DGPTM_Module_Metadata_File::is_module_critical()`
- Blockiert Deaktivierung mit Fehlermeldung
- Loggt Versuch als CRITICAL-Level Event

**Code**:
```php
// SCHUTZ: Prüfe ob kritisch (Flag ODER Config)
if ($metadata_manager->is_module_critical($module_id, $module_config)) {
    $this->add_admin_notice(
        sprintf(__('Modul "%s" ist als kritisch markiert...'), $module_name),
        'error'
    );
    dgptm_log("Versuch kritisches Modul zu deaktivieren - BLOCKIERT", 'critical');
    return false;
}
```

### Layer 3: Dependency-Manager-Schutz

**Datei**: `core/class-dependency-manager.php`
**Methode**: `can_deactivate_module()`
**Zeilen**: 319-354

**Funktionalität**:
- Zusätzliche Prüfung auf Dependency-Ebene
- Prüft Critical-Flag VOR Abhängigkeits-Prüfung
- Gibt detaillierte Fehlermeldung zurück

**Code**:
```php
// Prüfe Critical-Flag
if ($metadata->is_module_critical($module_id, $config)) {
    return [
        'can_deactivate' => false,
        'dependents' => [],
        'is_critical' => true,
        'message' => sprintf(
            __('Cannot deactivate: "%s" is marked as critical...'),
            $config['name'] ?? $module_id
        )
    ];
}
```

### Layer 4: Safe-Loader Auto-Deactivation-Schutz

**Datei**: `core/class-safe-loader.php`
**Methode**: `auto_deactivate_module()`
**Zeilen**: 407-452

**Funktionalität**:
- Verhindert automatische Deaktivierung bei Fehlern
- Sendet E-Mail-Warnung an Administrator
- Loggt Fehler mit höchster Priorität
- Speichert Fehlerdetails in `dgptm_suite_failed_activations`

**Code**:
```php
// SCHUTZ 1: Prüfe ob Modul als kritisch markiert ist
if ($metadata->is_module_critical($module_id)) {
    dgptm_log("CRITICAL-SCHUTZ - Modul wird NIEMALS deaktiviert!", 'error');

    // Fehler loggen
    $failed_activations[$module_id . '_critical'] = [
        'is_critical_module' => true,
        'protection_triggered' => true,
        'severity' => 'CRITICAL'
    ];

    // Admin per E-Mail warnen
    $this->send_critical_module_error_email($module_id, $error_info);

    return; // NIEMALS kritische Module deaktivieren!
}
```

### Layer 5: E-Mail-Benachrichtigung

**Datei**: `core/class-safe-loader.php`
**Methode**: `send_critical_module_error_email()`
**Zeilen**: 628-663

**Funktionalität**:
- Sendet detaillierte E-Mail an WordPress-Admin
- Enthält Fehlerdetails (Datei, Zeile, Nachricht)
- Listet empfohlene Maßnahmen auf
- Verlinkt Dashboard und System Logs

**E-Mail-Inhalt**:
```
KRITISCHER FEHLER in DGPTM Plugin Suite
=========================================

Modul: [module-id]
Status: CRITICAL - Automatische Deaktivierung verhindert
Zeit: [timestamp]

FEHLERDETAILS:
Fehler: [error message]
Datei: [file path]
Zeile: [line number]

MASSNAHMEN:
1. Prüfen Sie das WordPress Debug-Log
2. Prüfen Sie DGPTM Suite → System Logs
3. Das Modul wurde NICHT automatisch deaktiviert
4. Beheben Sie den Fehler schnellstmöglich
5. Bei Bedarf: Modul manuell deaktivieren

Dashboard: [link]
System Logs: [link]
```

### Layer 6: Massen-Deaktivierungs-Schutz

**Datei**: `core/class-safe-loader.php`
**Zeilen**: 454-479

**Funktionalität**:
- Verhindert dass mehr als 5 Module/Stunde deaktiviert werden
- Schützt vor systematischen Fehlern (z.B. fehlende WordPress-Funktionen)
- Sendet separate E-Mail-Warnung

**Code**:
```php
// Wenn in der letzten Stunde mehr als 5 Module deaktiviert wurden, STOPP!
if ($recent_count >= 5) {
    dgptm_log("MASSEN-DEAKTIVIERUNGS-SCHUTZ aktiviert!", 'critical');
    // Admin warnen per E-Mail
    // Modul NICHT deaktivieren
    return;
}
```

## Module als kritisch markieren

### Methode 1: Config-basiert (empfohlen für permanente kritische Module)

Bearbeite `module.json`:
```json
{
  "id": "my-module",
  "name": "My Critical Module",
  "critical": true
}
```

**Vorteile**:
- Permanent im Code gespeichert
- Versioniert mit dem Modul
- Kann nicht versehentlich entfernt werden

### Methode 2: Flag-basiert (empfohlen für temporär kritische Module)

Über das Dashboard:
1. Modul-Info-Button (ℹ️) klicken
2. Flag "Important" hinzufügen
3. Speichern

**Vorteile**:
- Schnell änderbar
- Keine Code-Änderung nötig
- Flexibel für temporäre Kritikalität

## Prüfung ob Modul kritisch ist

**Zentrale Methode**:
```php
$metadata = DGPTM_Module_Metadata_File::get_instance();
$is_critical = $metadata->is_module_critical($module_id, $config);
```

**Interne Logik**:
```php
public function is_module_critical($module_id, $config = null) {
    // Prüfe Config
    if (!empty($config['critical'])) {
        return true;
    }

    // Prüfe Flag
    $metadata = $this->get_module_metadata($module_id);
    $flags = $metadata['flags'] ?? [];

    foreach ($flags as $flag) {
        if ($flag['type'] === 'important') {
            return true;
        }
    }

    return false;
}
```

## Fehlerbehandlung bei kritischen Modulen

### Was passiert bei einem Fehler?

1. **Fehler wird abgefangen** (try-catch in Safe Loader)
2. **Auto-Deaktivierung wird verhindert** (Layer 4)
3. **Fehler wird geloggt** mit Severity CRITICAL
4. **Admin erhält E-Mail** mit vollständigen Details
5. **Modul bleibt aktiviert** - WordPress läuft weiter
6. **Fehlereintrag wird gespeichert** in `dgptm_suite_failed_activations`

### Admin-Aktionen nach Fehler

1. **Prüfen**: System Logs öffnen (`admin.php?page=dgptm-suite-logs`)
2. **Filtern**: Nach Modul-ID und Level "Critical" filtern
3. **Analysieren**: Fehlerdetails untersuchen (Datei, Zeile, Stack Trace)
4. **Beheben**: Code-Fehler korrigieren
5. **Optional**: Modul manuell deaktivieren (wenn Fehler nicht behebbar)

## System Logs

Alle Schutz-Aktivierungen werden geloggt:

**Beispiel-Logs**:
```
[CRITICAL] Versuch kritisches Modul 'crm-abruf' zu deaktivieren - BLOCKIERT (Flag oder Config)
[ERROR] Safe Loader: CRITICAL-SCHUTZ - Modul 'menu-control' wird NIEMALS deaktiviert!
[CRITICAL] Safe Loader: ADMIN-AKTION ERFORDERLICH - Bitte Fehler manuell beheben!
[WARNING] Safe Loader: SCHUTZ AKTIV - Modul bereits geladen und wird NICHT deaktiviert!
```

**Zugriff**: `DGPTM Suite → System Logs`

## Testing

### Test-Szenario 1: Deaktivierungs-Versuch via UI

1. Modul als critical markieren
2. Dashboard aufrufen
3. **Erwartet**: Button ist disabled, CRITICAL Badge wird angezeigt

### Test-Szenario 2: Fehler in kritischem Modul

1. Kritisches Modul mit Syntax-Fehler versehen
2. Modul aktivieren
3. **Erwartet**:
   - Modul bleibt aktiviert
   - Admin erhält E-Mail
   - Fehler wird in System Logs angezeigt
   - Eintrag in `dgptm_suite_failed_activations`

### Test-Szenario 3: AJAX-Versuch

1. Modul als critical markieren
2. JavaScript Console öffnen
3. AJAX-Call ausführen:
```javascript
jQuery.post(ajaxurl, {
    action: 'dgptm_toggle_module',
    module_id: 'crm-abruf',
    activate: false,
    nonce: dgptmSuite.nonce
});
```
4. **Erwartet**: Fehler-Response mit Nachricht über kritisches Modul

## Vorteile des Systems

1. **Mehrschichtige Sicherheit**: Selbst wenn ein Layer versagt, greifen andere
2. **Transparenz**: Alle Schutz-Aktivierungen werden geloggt
3. **Flexibilität**: Kritikalität via Config ODER Flag
4. **Stabilität**: WordPress bleibt auch bei Fehlern stabil
5. **Benachrichtigung**: Admin wird sofort über Probleme informiert
6. **Massen-Schutz**: Verhindert systemische Fehler

## Bekannte Limitierungen

1. **Manuelle Deaktivierung**: Admin kann kritische Module manuell in der Datenbank deaktivieren
2. **E-Mail-Abhängigkeit**: Benachrichtigung erfordert funktionierende `wp_mail()`
3. **Performance**: Mehrfache Prüfungen können minimal Performance kosten

## Wartung

### Regelmäßige Aufgaben

1. **Logs prüfen**: Mindestens wöchentlich System Logs auf CRITICAL-Level prüfen
2. **E-Mails überwachen**: DGPTM-bezogene E-Mails zeitnah bearbeiten
3. **Failed Activations**: Option `dgptm_suite_failed_activations` regelmäßig leeren

### Datenbank-Optionen

- `dgptm_suite_settings` - Modul-Aktivierungsstatus
- `dgptm_suite_failed_activations` - Fehler-Log für fehlgeschlagene Aktivierungen
- `dgptm_recent_auto_deactivations` - Transient für Massen-Deaktivierungs-Schutz

## Versionierung

- **Eingeführt**: v1.0.0
- **Letzte Aktualisierung**: 2025-11-29
- **Status**: Produktiv, vollständig getestet

## Support

Bei Problemen mit dem Critical Module Protection System:

1. System Logs prüfen
2. WordPress Debug-Log aktivieren (`WP_DEBUG` in `wp-config.php`)
3. `dgptm_suite_failed_activations` Option prüfen
4. E-Mail-Benachrichtigungen überprüfen

## Entwickler-Hinweise

### Neues kritisches Modul erstellen

```json
{
  "id": "new-critical-module",
  "name": "New Critical Module",
  "critical": true,
  "description": "This module is critical for system operation"
}
```

### Kritikalität programmatisch prüfen

```php
$metadata = DGPTM_Module_Metadata_File::get_instance();

if ($metadata->is_module_critical('module-id')) {
    // Spezielle Behandlung für kritisches Modul
}
```

### Custom Error Handling für kritische Module

```php
add_action('dgptm_suite_critical_module_error', function($module_id, $error) {
    // Custom Fehlerbehandlung
}, 10, 2);
```
