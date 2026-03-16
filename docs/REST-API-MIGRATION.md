# REST API Migration Plan

Langfristige Migration der AJAX-Handler zu WordPress REST API Endpoints.

## Status: Dokumentation (kein Code-Umbau in dieser Runde)

## Vorteile der REST API

- **Standardisiert:** Nutzung der WP REST API Infrastruktur (Permissions, Routing, Schema)
- **Testbar:** Endpoints lassen sich mit Tools wie Postman oder curl testen
- **Versionierbar:** `/wp-json/dgptm/v1/...` erlaubt API-Versionierung
- **Extern nutzbar:** Andere Plugins/Apps koennen die API nutzen
- **Typisiert:** Schema-Validierung fuer Request/Response

## Mapping: AJAX → REST

### Modul-Operationen (admin/class-plugin-manager.php)

| AJAX Action | REST Endpoint | Methode |
|---|---|---|
| `dgptm_toggle_module` | `/dgptm/v1/modules/{id}/toggle` | POST |
| `dgptm_export_module` | `/dgptm/v1/modules/{id}/export` | GET |
| `dgptm_get_module_info` | `/dgptm/v1/modules/{id}` | GET |
| `dgptm_create_module` | `/dgptm/v1/modules` | POST |
| `dgptm_test_module` | `/dgptm/v1/modules/{id}/test` | POST |
| `dgptm_delete_module` | `/dgptm/v1/modules/{id}` | DELETE |

### Metadata

| AJAX Action | REST Endpoint | Methode |
|---|---|---|
| `dgptm_add_flag` | `/dgptm/v1/modules/{id}/flags` | POST |
| `dgptm_remove_flag` | `/dgptm/v1/modules/{id}/flags/{flag}` | DELETE |
| `dgptm_set_comment` | `/dgptm/v1/modules/{id}/comment` | PUT |
| `dgptm_switch_version` | `/dgptm/v1/modules/{id}/version` | PUT |
| `dgptm_link_test_version` | `/dgptm/v1/modules/{id}/test-version` | POST |

### Checkout

| AJAX Action | REST Endpoint | Methode |
|---|---|---|
| `dgptm_checkout_module` | `/dgptm/v1/modules/{id}/checkout` | POST |
| `dgptm_checkin_module` | `/dgptm/v1/modules/{id}/checkin` | POST |
| `dgptm_cancel_checkout` | `/dgptm/v1/modules/{id}/checkout` | DELETE |

### Logs

| AJAX Action | REST Endpoint | Methode |
|---|---|---|
| `dgptm_get_logs` | `/dgptm/v1/logs` | GET |
| `dgptm_clear_logs` | `/dgptm/v1/logs` | DELETE |

## Implementierungshinweise

### Namespace registrieren
```php
add_action('rest_api_init', function() {
    register_rest_route('dgptm/v1', '/modules', [...]);
});
```

### Permission Callback
```php
'permission_callback' => function() {
    return current_user_can('manage_options');
}
```

### Schrittweise Migration
1. REST Endpoints parallel zu AJAX-Handlern erstellen
2. Frontend schrittweise auf fetch()/REST umstellen
3. AJAX-Handler als deprecated markieren
4. Nach Testphase AJAX-Handler entfernen

### Priorisierung
- **Phase 1:** Modul-Info (GET) und Logs (GET) - einfach, readonly
- **Phase 2:** Toggle und Metadata - zentrale Admin-Funktionen
- **Phase 3:** Checkout und Export - komplexere Workflows
