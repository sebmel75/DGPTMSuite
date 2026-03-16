# Settings-Patterns fuer DGPTM Module

## Empfohlenes Pattern

Module sollten den zentralen `DGPTM_Module_Settings_Manager` nutzen statt eigene Options zu verwalten.

### Registrierung

```php
// In der __construct() Methode des Moduls:
add_action('dgptm_suite_modules_loaded', function() {
    $sm = DGPTM_Module_Settings_Manager::get_instance();
    $sm->register_module('mein-modul', [
        'label'    => 'Mein Modul',
        'sections' => [
            'allgemein' => [
                'title'  => 'Allgemeine Einstellungen',
                'fields' => [
                    'api_url' => [
                        'type'    => 'text',
                        'label'   => 'API URL',
                        'default' => '',
                    ],
                    'aktiv' => [
                        'type'    => 'checkbox',
                        'label'   => 'Feature aktivieren',
                        'default' => false,
                    ],
                ],
            ],
        ],
    ]);
});
```

### Settings lesen

```php
$sm = DGPTM_Module_Settings_Manager::get_instance();
$settings = $sm->get_settings('mein-modul');
$api_url = $settings['api_url'] ?? '';
```

### Settings schreiben

```php
$sm = DGPTM_Module_Settings_Manager::get_instance();
$sm->update_settings('mein-modul', [
    'api_url' => 'https://api.example.com',
    'aktiv'   => true,
]);
// Loest automatisch den Hook dgptm_module_settings_saved aus
```

## Vorteile

- **Zentrale Verwaltung:** Alle Modul-Settings unter einem Menue
- **Einheitliche UI:** Automatisch generiertes Settings-Formular
- **Debug-Level:** Integrierte Log-Level-Steuerung pro Modul
- **Hooks:** `dgptm_module_settings_saved` feuert nach jedem Update
- **Cache:** Internes Caching der Settings im Request

## Anti-Patterns (vermeiden)

### Eigene Options-Keys
```php
// NICHT empfohlen:
update_option('mein_modul_api_url', $url);
update_option('mein_modul_aktiv', true);

// STATTDESSEN:
$sm->update_settings('mein-modul', ['api_url' => $url, 'aktiv' => true]);
```

### Settings in module.json
```json
// NICHT empfohlen - module.json ist fuer Metadaten, nicht fuer Runtime-Settings
{
    "settings": { "api_url": "..." }
}
```

### Direkte wp_options Manipulation
```php
// NICHT empfohlen:
$settings = get_option('dgptm_suite_settings');
$settings['custom_thing'] = 'value';
update_option('dgptm_suite_settings', $settings);
// Das Hauptsettings-Objekt ist reserviert fuer die Suite-Kerneinstellungen
```

## Migration bestehender Module

Fuer Module die noch eigene Options nutzen:

1. Settings-Definition ueber `register_module()` hinzufuegen
2. Bestehende Options mit `get_option()` lesen und ueber `update_settings()` migrieren
3. Migration einmalig ausfuehren (z.B. via Versions-Check)
4. Alte Options nach Pruefphase entfernen

### Beispiel-Migration

```php
// Einmalige Migration beim Laden:
$sm = DGPTM_Module_Settings_Manager::get_instance();
$current = $sm->get_settings('mein-modul');

if (empty($current) && $legacy = get_option('mein_modul_settings')) {
    $sm->update_settings('mein-modul', $legacy);
    delete_option('mein_modul_settings');
    dgptm_log_info('Settings migriert', 'mein-modul');
}
```
