# DGPTM Role Manager

**Version:** 1.0.0
**Kategorie:** Utilities
**Autor:** Sebastian Melzer

## Beschreibung

Ersetzt das Members Plugin mit fokussierten Kernfunktionen: Backend-Zugriffskontrolle, Multiple Rollen Support und Toolbar-Management.

## Features

✅ **Backend-Zugriffssperre** - Nur Administrator, Editor und Author dürfen ins wp-admin
✅ **Multiple Rollen** - Benutzer können mehrere Rollen gleichzeitig haben
✅ **Toolbar-Kontrolle** - Toolbar nur für Administrator und Editor sichtbar
✅ **Saubere Integration** - Nutzt WordPress-Standardfunktionen
✅ **Kein Overhead** - Leichtgewichtig und performant

## Installation

1. Modul im DGPTM Suite Dashboard aktivieren
2. Fertig! Keine weitere Konfiguration nötig

## Funktionsweise

### 1. Backend-Zugriffssperre

**Erlaubte Rollen:**
- Administrator
- Editor
- Author

**Verhalten:**
- Benutzer mit anderen Rollen werden automatisch zum Frontend (home_url) weitergeleitet
- AJAX-Requests werden nicht blockiert
- admin-ajax.php und admin-post.php bleiben zugänglich (für Frontend-Forms)

**Ausnahme:** Benutzer mit aktiver Frontend Page Editor Session haben temporär Zugriff

### 2. Toolbar-Kontrolle

**Toolbar sichtbar für:**
- Administrator
- Editor

**Toolbar versteckt für:**
- Author
- Contributor
- Subscriber
- Alle anderen Rollen

### 3. Multiple Rollen

**Verwendung:**

1. **WordPress Admin → Benutzer → Bearbeiten**
2. **Scrollen zu "Zusätzliche Rollen"**
3. **Mehrere Rollen auswählen**
4. **Speichern**

**Wichtig:**
- Benutzer erhält ALLE Capabilities der ausgewählten Rollen
- Höchste Berechtigung gewinnt
- Admin kann sich nicht selbst die Admin-Rolle entfernen

**Beispiel:**
```
User hat Rollen: [subscriber, author]
→ Kann Beiträge erstellen (author)
→ Hat Backend-Zugriff (author)
→ Sieht keine Toolbar (nicht editor/admin)
```

## Anwendungsfälle

### Fall 1: Subscriber mit speziellen Rechten

**Szenario:** Mitglied soll Newsletter versenden, aber kein Backend-Zugriff

**Setup:**
1. User bleibt Subscriber
2. Für Newsletter: Nutze Frontend-Tools oder spezielles Modul
3. Kein Backend-Zugriff nötig

### Fall 2: Author der auch moderiert

**Szenario:** Content-Creator der auch Kommentare moderieren soll

**Setup:**
1. Rolle 1: Author (für Beiträge)
2. Rolle 2: Eigene Custom Role mit `moderate_comments`
3. User hat Backend-Zugriff (author) + Moderationsrechte

### Fall 3: Externes Team ohne Backend

**Szenario:** Freelancer sollen nur über Frontend arbeiten

**Setup:**
1. Rolle: Subscriber
2. Nutze Frontend Page Editor für Seiten
3. Kein Backend-Zugriff
4. Toolbar versteckt

## Technische Details

### Backend-Sperre

```php
// Hook: admin_init (Priority 10)
// Prüfung: Benutzer-Rollen gegen Whitelist
// Action: wp_redirect(home_url()) bei Verstoß
```

**Whitelist:**
```php
['administrator', 'editor', 'author']
```

**Bypass:**
- AJAX-Requests
- admin-ajax.php
- admin-post.php

### Toolbar-Kontrolle

```php
// Hook: after_setup_theme
// Funktion: show_admin_bar(false)
```

**Whitelist:**
```php
['administrator', 'editor']
```

### Multiple Rollen

**Speicherung:**
- WordPress User-Meta: `wp_capabilities`
- Format: `{"role1":true,"role2":true}`

**Capabilities:**
- Werden von WordPress automatisch zusammengeführt
- Höchste Berechtigung gilt

### Sicherheit

✅ **Nonce-Prüfung** bei Rollen-Änderungen
✅ **Capability-Check** (`promote_users` erforderlich)
✅ **Self-Protection** (Admin kann sich nicht Admin-Rolle entfernen)
✅ **Input-Sanitization** bei Rollen-Speicherung

## Integration mit anderen Modulen

### Mit Frontend Page Editor

**Kompatibilität:** ✅ Vollständig kompatibel

Das Frontend Page Editor Modul ändert temporär die User-Rolle auf "editor". Dies funktioniert perfekt mit Role Manager:

```
1. Subscriber klickt "Mit Elementor bearbeiten"
2. Rolle wird temporär zu "editor"
3. Backend-Zugriff gewährt (editor ist erlaubt)
4. Toolbar wird angezeigt (editor ist erlaubt)
5. Nach Session-Ende: Zurück zu "subscriber"
6. Backend gesperrt, Toolbar versteckt
```

### Mit Menu Control

Verwende Menu Control für erweiterte Menü-Sichtbarkeit basierend auf Rollen.

### Mit OTP Login

Passwortloses Login funktioniert normal. Backend-Sperre greift nach Login je nach Rolle.

## Unterschiede zu Members Plugin

| Feature | Members Plugin | DGPTM Role Manager |
|---------|---------------|-------------------|
| Backend-Sperre | ✅ Konfigurierbar | ✅ Fest: Admin/Editor/Author |
| Multiple Rollen | ✅ Ja | ✅ Ja |
| Toolbar-Kontrolle | ✅ Ja | ✅ Fest: Admin/Editor |
| Capability-Editor | ✅ Ja | ❌ Nein (nutze Code) |
| Content-Permissions | ✅ Ja | ❌ Nicht nötig |
| Private Site | ✅ Ja | ❌ Nutze andere Tools |
| Größe | ~500 KB | ~15 KB |

**Philosophie:** Weniger Features, bessere Performance, einfachere Wartung.

## Anpassung

### Backend-Zugriff für weitere Rollen

```php
// In role-manager.php, Zeile 19:
private $backend_allowed_roles = ['administrator', 'editor', 'author', 'custom_role'];
```

### Toolbar für weitere Rollen

```php
// In role-manager.php, Zeile 22:
private $toolbar_allowed_roles = ['administrator', 'editor', 'custom_role'];
```

### Programmatisch Rollen zuweisen

```php
$user = new WP_User($user_id);
$user->add_role('author');
$user->add_role('subscriber');
```

### Programmatisch Rollen entfernen

```php
$user = new WP_User($user_id);
$user->remove_role('subscriber');
```

## Troubleshooting

### User wird ins Frontend weitergeleitet obwohl Author

**Ursache:** Rolle wurde nicht korrekt gesetzt

**Lösung:**
1. WordPress Admin → Benutzer → Bearbeiten
2. Prüfe Rollen-Checkboxen
3. Speichern

### Toolbar verschwindet für Author

**Erwartetes Verhalten:** Authors sehen standardmäßig keine Toolbar

**Lösung:** Author-Rolle zur `$toolbar_allowed_roles` Whitelist hinzufügen

### Multiple Rollen werden nicht gespeichert

**Ursache:** Andere Plugins überschreiben Rollen

**Lösung:** Role Manager Modul später laden oder andere Plugins prüfen

### Admin sieht "Zusätzliche Rollen" nicht

**Ursache:** User hat nicht `promote_users` Capability

**Lösung:** Nur echte Administratoren können Rollen ändern

## Best Practices

### 1. Minimale Berechtigungen

Gebe Benutzern nur die Rollen die sie wirklich brauchen.

### 2. Teste Rollenkombinationen

Teste immer mit einem Test-User bevor du echten Benutzern Rollen gibst.

### 3. Dokumentiere Custom Roles

Wenn du eigene Rollen erstellst, dokumentiere welche Capabilities sie haben.

### 4. Nutze Role-Hierarchy

```
Administrator > Editor > Author > Contributor > Subscriber
```

### 5. Backend vs Frontend

Überlege ob Benutzer wirklich Backend-Zugriff brauchen oder Frontend-Tools ausreichen.

## Support

**Voraussetzungen prüfen:**
- ✅ WordPress 5.8+
- ✅ PHP 7.4+
- ✅ Modul aktiviert

**Bei Problemen:**
1. Überprüfe User-Rollen im Admin
2. Teste mit Test-User
3. Prüfe Debug-Log

## Changelog

### Version 1.0.0 (2025-11-21)
- ✅ Initiales Release
- ✅ Backend-Zugriffssperre (Admin/Editor/Author)
- ✅ Multiple Rollen Support
- ✅ Toolbar-Kontrolle (Admin/Editor)
- ✅ User-Profil Integration
- ✅ Self-Protection für Admins

## Technische Spezifikationen

**Modul-ID:** `role-manager`
**Kategorie:** Utilities
**Version:** 1.0.0
**Haupt-Datei:** `role-manager.php`
**Dependencies:** Keine
**PHP:** 7.4+
**WordPress:** 5.8+
**Dateigröße:** ~15 KB
**Performance:** Minimal (3 Hooks, keine DB-Queries)
