# DGPTM Suite - Hook-Referenz

Alle verfuegbaren `do_action()` und `apply_filters()` Hooks der DGPTM Plugin Suite.

## Modul-Lifecycle Hooks

### `dgptm_suite_modules_loaded`
Feuert nachdem alle Module geladen wurden.
- **Parameter:** `$loaded_modules` (array) - Alle geladenen Module
- **Datei:** `core/class-module-loader.php`

### `dgptm_suite_module_loaded`
Feuert nachdem ein einzelnes Modul geladen wurde.
- **Parameter:** `$module_id` (string), `$config` (array)
- **Datei:** `core/class-module-loader.php`

### `dgptm_suite_module_activated`
Feuert nachdem ein Modul aktiviert wurde.
- **Parameter:** `$module_id` (string)
- **Datei:** `admin/class-plugin-manager.php`

### `dgptm_suite_module_deactivated`
Feuert nachdem ein Modul deaktiviert wurde.
- **Parameter:** `$module_id` (string)
- **Datei:** `admin/class-plugin-manager.php`

### `dgptm_suite_module_reinit`
Feuert wenn ein Modul re-initialisiert wird.
- **Parameter:** `$module_id` (string), `$config` (array)
- **Datei:** `core/class-module-loader.php`

### `dgptm_suite_module_reinit_{$module_id}`
Modul-spezifische Variante von `dgptm_suite_module_reinit`.
- **Parameter:** `$config` (array)
- **Datei:** `core/class-module-loader.php`

### `dgptm_suite_module_init_cycle`
Feuert bei jedem Init-Zyklus eines Moduls.
- **Parameter:** `$module_id` (string)
- **Datei:** `core/class-module-loader.php`

## Metadata & Config Hooks

### `dgptm_suite_module_config_updated`
Feuert nachdem die module.json eines Moduls aktualisiert wurde.
- **Parameter:** `$module_id` (string), `$config` (array)
- **Datei:** `core/class-module-metadata-file.php`

### `dgptm_suite_module_metadata_updated`
Feuert nachdem Modul-Metadaten aktualisiert wurden (Legacy DB-basiert).
- **Parameter:** `$module_id` (string), `$metadata` (array)
- **Datei:** `core/class-module-metadata.php`

### `dgptm_module_settings_saved`
Feuert nachdem Modul-Settings gespeichert wurden.
- **Parameter:** `$module_id` (string), `$settings` (array)
- **Datei:** `core/class-module-settings-manager.php`

## Checkout Hooks

### `dgptm_suite_module_checked_in`
Feuert nachdem ein Modul eingecheckt wurde.
- **Parameter:** `$module_id` (string), `$checkout_info` (array), `$result` (array)
- **Datei:** `core/class-checkout-manager.php`

### `dgptm_suite_checkout_cancelled`
Feuert wenn ein Checkout abgebrochen wird.
- **Parameter:** `$module_id` (string), `$checkout_id` (string)
- **Datei:** `core/class-checkout-manager.php`

## Update Hooks

### `dgptm_suite_update_check_performed`
Feuert nachdem ein Update-Check durchgefuehrt wurde.
- **Datei:** `core/class-update-manager.php`

### `dgptm_suite_module_updated`
Feuert nachdem ein Modul aktualisiert wurde.
- **Parameter:** `$module_id` (string), `$update_data` (array)
- **Datei:** `core/class-update-manager.php`

## Business-Logik Hooks

### `dgptm_user_data_loaded`
Feuert nachdem Benutzerdaten aus der Zoho CRM API geladen wurden.
- **Parameter:** `$zoho_data` (array), `$user_id` (int)
- **Datei:** `modules/core-infrastructure/crm-abruf/crmabruf.php`
- **Beispiel:**
```php
add_action('dgptm_user_data_loaded', function($data, $user_id) {
    // Mitgliedsstatus pruefen, Custom Logic ausfuehren
}, 10, 2);
```

### `dgptm_survey_completed`
Feuert nachdem eine Umfrage vollstaendig ausgefuellt wurde.
- **Parameter:** `$survey_id` (int), `$response_id` (int), `$survey` (object)
- **Datei:** `modules/business/umfragen/includes/class-survey-frontend.php`
- **Beispiel:**
```php
add_action('dgptm_survey_completed', function($survey_id, $response_id, $survey) {
    // E-Mail-Benachrichtigung senden, Statistik aktualisieren
}, 10, 3);
```

### `dgptm_vote_cast`
Feuert nachdem eine Stimme erfolgreich abgegeben wurde.
- **Parameter:** `$question_id` (int), `$choices` (array), `$user_id` (int), `$poll_id` (int)
- **Datei:** `modules/business/abstimmen-addon/includes/ajax/vote.php`
- **Beispiel:**
```php
add_action('dgptm_vote_cast', function($qid, $choices, $user_id, $poll_id) {
    // Teilnehmerzahl aktualisieren, Live-Ergebnisse pushen
}, 10, 4);
```

### `dgptm_fortbildung_created`
Feuert nachdem ein Fortbildungseintrag erstellt wurde (z.B. nach Webinar-Abschluss).
- **Parameter:** `$fortbildung_id` (int), `$user_id` (int), `$webinar_id` (int)
- **Datei:** `modules/content/vimeo-webinare/dgptm-vimeo-webinare.php`
- **Beispiel:**
```php
add_action('dgptm_fortbildung_created', function($fobi_id, $user_id, $webinar_id) {
    // Fortbildungspunkte-Konto aktualisieren
}, 10, 3);
```

## Filter Hooks

### `dgptm_allow_iframe`
Erlaubt/verhindert iFrame-Einbettung fuer Zoho-Felder mit URLs.
- **Parameter:** `$allow` (bool), `$url` (string), `$fieldname` (string), `$data` (array)
- **Datei:** `modules/core-infrastructure/crm-abruf/crmabruf.php`

### `ebcpdgptm_render_translator`
Rendert den EBCP-Uebersetzer.
- **Datei:** `modules/content/ebcp-guidelines/ebcp.php`
