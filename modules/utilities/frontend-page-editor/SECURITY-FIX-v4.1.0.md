# Frontend Page Editor - CRITICAL Security Fix v4.1.0

## Problem (v4.0.0 und früher)

**KRITISCHE SICHERHEITSLÜCKE**: Benutzer, denen nur eine einzelne Seite zur Bearbeitung zugewiesen wurde, erhielten durch die Funktion `grant_editing_capabilities()` vollständigen Zugriff auf das gesamte WordPress-Backend.

### Was war das Problem?

Die alte Implementierung gewährte folgende Capabilities:

```php
$allcaps['edit_pages'] = true;              // ❌ PROBLEM: Zugriff auf ALLE Seiten
$allcaps['edit_published_pages'] = true;    // ❌ PROBLEM: Zugriff auf ALLE publizierten Seiten
```

Diese Capabilities erlaubten es dem User:
- Alle Seiten im Backend zu sehen
- Auf andere Seiten zu navigieren
- Das Dashboard und andere Admin-Bereiche zu besuchen
- Potenziell sensible Daten einzusehen

### Warum ist das kritisch?

Ein User, der z.B. nur die "Kontakt"-Seite bearbeiten darf, konnte:
1. Ins Backend navigieren
2. Alle Seiten sehen und öffnen (auch wenn er sie nicht speichern konnte)
3. Admin-Menüs durchsuchen
4. Andere Posts/Pages einsehen
5. Medien-Bibliothek durchsuchen

**Beispiel-Szenario**:
- User "Hans" soll nur die Seite "Team" bearbeiten
- Hans klickt auf seinen Edit-Link und landet im Elementor-Editor
- Hans klickt auf das WordPress-Logo → kommt ins Dashboard
- Hans klickt auf "Seiten" → sieht ALLE Seiten der Website
- Hans kann auf andere Seiten klicken und deren Inhalte sehen

## Lösung (v4.1.0)

### 1. Entfernung der breiten Capabilities

Die Funktion `grant_editing_capabilities()` gewährt jetzt NUR noch:

```php
$allcaps['upload_files'] = true;  // Nur für Medien-Upload (notwendig)
$allcaps['read'] = true;          // Basis-Zugriff (notwendig)

// Elementor-spezifisch (nur wenn Elementor-Editor aktiv)
if (isset($_GET['action']) && $_GET['action'] === 'elementor') {
    $allcaps['elementor'] = true;
    $allcaps['edit_with_elementor'] = true;
}
```

Die gefährlichen `edit_pages` und `edit_published_pages` werden **NICHT** mehr gewährt!

### 2. Backend-Zugriffsbeschränkung

Neue Funktion: `restrict_backend_access()`

**Was sie tut:**
- Prüft bei jedem Admin-Request, ob der User eine aktive Edit-Session hat
- Erlaubt nur Zugriff auf `post.php` (Editor) und notwendige AJAX-Endpoints
- Blockiert Zugriff auf:
  - Dashboard (`index.php`)
  - Seiten-Übersicht (`edit.php`)
  - Einstellungen
  - Plugins/Themes
  - Alle anderen Admin-Bereiche

**Redirect-Logik:**
```php
// Erlaubte Admin-Seiten
$allowed_pages = ['post.php', 'admin-ajax.php', 'async-upload.php', 'media-upload.php'];

if (!in_array($pagenow, $allowed_pages)) {
    // User wird zurück zum Editor seiner zugewiesenen Seite geleitet
    wp_redirect($edit_url);
    exit;
}
```

**Seiten-Validierung:**
```php
// Wenn User auf post.php ist, muss es die richtige Seite sein
if ($pagenow === 'post.php' && $current_page && $current_page != $editing_page) {
    wp_die('Sie haben keine Berechtigung, diese Seite zu bearbeiten.');
}
```

### 3. Admin-Menüs verstecken

Neue Funktion: `hide_admin_menus()`

```php
// Entfernt ALLE Admin-Menüs für eingeschränkte User
global $menu, $submenu;
$menu = [];
$submenu = [];
```

**Effekt**: Der User sieht keine linke Seitenleiste mehr, nur den Editor.

### 4. Admin Bar bereinigen

Neue Funktion: `hide_admin_bar_items()`

Versteckt via CSS:
- Alle Admin Bar Items außer "Mein Account"
- Das komplette Admin-Menü (Seitenleiste)
- Passt Layout an (kein Seitenleisten-Abstand mehr)

```css
#wpadminbar .ab-top-menu > li:not(#wp-admin-bar-my-account) { display: none !important; }
#adminmenu, #adminmenuwrap { display: none !important; }
#wpcontent, #wpfooter { margin-left: 0 !important; }
```

### 5. JavaScript Navigation-Blocker

Neue Funktion: `enqueue_security_script()`

Blockiert alle Klicks auf Admin-Links, die nicht erlaubt sind:

```javascript
// Erlaubte URLs
var allowed = [
    'post.php',
    'admin-ajax.php',
    'async-upload.php',
    'media-upload.php',
    'elementor'
];

// Alle anderen werden blockiert
if (!isAllowed) {
    e.preventDefault();
    alert('Navigation im Admin-Bereich ist eingeschränkt.');
    return false;
}
```

### 6. Verbesserte map_meta_cap

Die Funktion `map_page_capabilities()` bleibt unverändert und arbeitet jetzt korrekt:

```php
// Erlaubt Zugriff nur auf die spezifische Seite
if ($this->user_can_edit_page($user_id, $post_id) && $editing_page == $post_id) {
    return ['exist'];  // Jeder eingeloggte User hat 'exist'
}
```

Diese Funktion wird jetzt durch WordPress korrekt genutzt, weil keine breiten Capabilities mehr gewährt werden.

## Schutz-Ebenen (Defense in Depth)

Das neue System schützt auf **5 Ebenen**:

1. **Capability-Ebene**: Keine breiten `edit_pages` Capabilities mehr
2. **Redirect-Ebene**: Automatische Umleitung bei falschem Admin-Bereich
3. **Access-Control-Ebene**: `wp_die()` bei Versuch, falsche Seite zu öffnen
4. **UI-Ebene**: Admin-Menüs werden komplett entfernt
5. **JavaScript-Ebene**: Zusätzlicher Client-Side Schutz

## Was Benutzer jetzt sehen

### Normale Workflow (erlaubt):

1. User klickt auf "Bearbeiten"-Link im Frontend
2. User wird zu `post.php?post=123&action=elementor` geleitet
3. User sieht NUR den Elementor/WP Editor
4. Keine Admin-Menüs sichtbar
5. Admin Bar zeigt nur "Mein Account"
6. User kann Seite bearbeiten und speichern
7. Nach Speichern bleibt User im Editor

### Blockierte Aktionen:

❌ User versucht, WordPress-Logo zu klicken → Kein Link vorhanden (ausgeblendet)
❌ User versucht, `/wp-admin/` direkt aufzurufen → Redirect zurück zum Editor
❌ User versucht, `/wp-admin/edit.php` aufzurufen → Redirect zurück zum Editor
❌ User gibt `/wp-admin/post.php?post=999` ein (andere Seite) → `wp_die()` Fehler
❌ User versucht, auf Admin-Link zu klicken → JavaScript blockiert + Alert

## AJAX-Kompatibilität

Folgende AJAX-Actions sind erlaubt (notwendig für Editor-Funktionalität):

```php
$allowed_actions = [
    'elementor_ajax',      // Elementor Editor
    'heartbeat',           // WordPress Heartbeat
    'upload-attachment',   // Medien-Upload
    'query-attachments',   // Medien-Galerie
    'save-attachment',     // Medien speichern
    'save-attachment-compat',
    'editpost',            // Seite speichern
    'inline-save',         // Quick Edit
    'wp_link_ajax',        // Link-Dialog
    'autocomplete-user'    // User-Suche
];
```

Alle anderen AJAX-Actions werden durch `restrict_backend_access()` blockiert, außer der User hat Admin-Rechte.

## Ausnahmen (nicht betroffen)

Die Sicherheitsrestriktionen gelten **NICHT** für:

- Administratoren (`manage_options` Capability)
- Editoren (`edit_others_pages` Capability)

Diese User-Rollen können weiterhin normal im Backend navigieren.

## Upgrade-Hinweise

### Von v4.0.0 auf v4.1.0:

1. **Keine Breaking Changes für normale Nutzung**
2. **User mit zugewiesenen Seiten**: Müssen weiterhin den Frontend-Editor-Link verwenden
3. **Admin-Workflow bleibt gleich**: Seiten werden weiterhin im User-Profil zugewiesen
4. **Session-Mechanismus bleibt gleich**: 1-Stunde Transient

### Was getestet werden sollte:

✅ User kann zugewiesene Seite öffnen und bearbeiten
✅ User kann Seite speichern
✅ User kann Medien hochladen
✅ User kann keine anderen Seiten öffnen
✅ User wird bei Versuch umgeleitet/blockiert
✅ Elementor-Editor funktioniert vollständig
✅ WordPress-Editor funktioniert vollständig
✅ Admin-User haben weiterhin vollen Zugriff

## Technische Details

### Session-Mechanismus

Die Edit-Session wird über WordPress Transients verwaltet:

```php
// Session setzen (1 Stunde gültig)
set_transient('dgptm_editing_' . $user_id, $page_id, 3600);

// Session prüfen
$editing_page = get_transient('dgptm_editing_' . $user_id);
```

**Wichtig**: Die Session ist an einen spezifischen User UND eine spezifische Page-ID gebunden.

### Capability Checks

Alle Sicherheitsfunktionen führen mehrfache Checks durch:

```php
// 1. Admin/Editor?
if (current_user_can('manage_options') || current_user_can('edit_others_pages')) {
    return;  // Keine Restriktionen
}

// 2. Hat User eine Edit-Session?
$editing_page = get_transient('dgptm_editing_' . $user_id);
if (!$editing_page) {
    // Blockieren oder Umleiten
}

// 3. Ist es die richtige Seite?
if ($current_page != $editing_page) {
    wp_die();
}

// 4. Hat User Berechtigung für diese Seite?
if (!$this->user_can_edit_page($user_id, $page_id)) {
    wp_die();
}
```

## Performance-Überlegungen

Die neuen Sicherheitschecks laufen auf folgenden Hooks:

- `admin_init` (Priority 1) - Sehr früh, kaum Performance-Impact
- `admin_menu` (Priority 999) - Nach allen anderen Menüs
- `admin_head` (Priority 999) - Nur CSS-Ausgabe
- `admin_enqueue_scripts` (Priority 999) - Inline JavaScript

**Impact**: Minimal, da die Checks nur im Admin-Bereich und nur für Nicht-Admin-User ausgeführt werden.

## Zusammenfassung

Version 4.1.0 behebt eine **kritische Sicherheitslücke**, die es eingeschränkten Usern erlaubte, das gesamte WordPress-Backend zu durchsuchen.

Die neue Implementierung:
- ✅ Entfernt gefährliche Capabilities
- ✅ Erzwingt strikte Backend-Zugriffskontrolle
- ✅ Versteckt Admin-UI komplett
- ✅ Blockiert Navigation zu anderen Bereichen
- ✅ Erlaubt nur Zugriff auf die zugewiesene Seite
- ✅ Behält volle Editor-Funktionalität bei
- ✅ Funktioniert mit Elementor und WordPress-Editor
- ✅ Keine Breaking Changes für Admins

**Empfehlung**: Sofortiges Update auf v4.1.0 für alle Installationen, die den Frontend Page Editor verwenden!
