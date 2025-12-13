# Frontend Seiteneditor

**Version:** 1.0.0
**Kategorie:** Utilities
**Autor:** Sebastian Melzer

## Beschreibung

Ermöglicht Benutzern die Bearbeitung ausgewählter Seiten mit Elementor **komplett im Frontend**, ohne dass sie Zugriff auf wp-admin benötigen.

## Features

✅ Elementor-Bearbeitung **komplett im Frontend** (keine wp-admin-Umleitung)
✅ Seitenzuweisung über ACF User-Meta
✅ Automatische Berechtigung für Seiten-Autoren
✅ Rollenbasierte Berechtigungen (Admin/Editor/Moderator)
✅ Sicherheitsgeprüfter Zugriff mit Nonces und Transients
✅ Temporäre Capabilities während Edit-Session (60 Min)
✅ Shortcode für Zugriffsprüfung (gibt 1 oder 0 zurück)
✅ Modernes, responsives Design

## Abhängigkeiten

**Erforderlich:**
- Elementor Plugin
- Advanced Custom Fields (ACF) Plugin

**WordPress:**
- WordPress 5.8+
- PHP 7.4+

## Installation

1. Modul im DGPTM Suite Dashboard aktivieren
2. Elementor und ACF müssen installiert und aktiv sein
3. Fertig! Das Modul funktioniert sofort.

## Shortcodes

### 1. `[frontend_page_editor]`

Zeigt die Liste aller bearbeitbaren Seiten für den aktuellen Benutzer an.

**Verwendung:**
```
[frontend_page_editor]
```

**Ausgabe:**
- Karten-Grid mit allen zugewiesenen Seiten
- "Mit Elementor bearbeiten"-Button für Elementor-Seiten
- "Im Editor bearbeiten"-Button für normale Seiten
- "Ansehen"-Button zum Preview
- Badge "Elementor" für Elementor-fähige Seiten
- Letzte Bearbeitung anzeigen

---

### 2. `[has_page_edit_access]`

Gibt `1` zurück, wenn der Benutzer Zugriff auf mindestens eine Seite hat, sonst `0`.

**Verwendung:**
```
[has_page_edit_access]
```

**Rückgabewerte:**
- `1` = Benutzer hat Zugriff auf mindestens eine Seite
- `0` = Benutzer hat keinen Zugriff

**Beispiel mit Conditional Logic (z.B. Elementor Pro):**
```
Wenn [has_page_edit_access] = 1 → Zeige Seiten-Editor-Link
Wenn [has_page_edit_access] = 0 → Zeige Hinweis "Keine Bearbeitungsrechte"
```

## Berechtigungssystem

### 1. Administratoren
- Haben automatisch Zugriff auf **alle Seiten** (inkl. Entwürfe)
- Keine manuelle Zuweisung nötig
- Sehen alle Seiten im Mitgliederbereich

### 2. Editoren & Moderatoren
- Haben automatisch Zugriff auf **alle veröffentlichten Seiten**
- Keine manuelle Zuweisung nötig
- Sehen alle veröffentlichten Seiten

### 3. Seiten-Autoren
- Benutzer die als **Author** einer Seite eingetragen sind
- Haben automatisch Zugriff auf **ihre eigenen Seiten**
- Keine manuelle Zuweisung nötig

### 4. Normale Benutzer (mit Zuweisung)
- Benötigen **manuelle Seitenzuweisung** über ACF User-Meta
- Sehen nur die ihnen zugewiesenen Seiten
- Sehen Seiten bei denen sie Author sind

## Seitenzuweisung (ACF User-Meta)

### Für Administratoren

**Zuweisen:**
1. WordPress Admin → **Benutzer**
2. Benutzer auswählen → **Bearbeiten**
3. Feld **"Bearbeitbare Seiten"** anzeigen
4. Seiten auswählen (Mehrfachauswahl möglich)
5. **Aktualisieren**

**Hinweis:** Das Feld ist nur für Administratoren sichtbar und nur bei Nicht-Admin-Benutzern sichtbar.

## Workflow

### Für Benutzer (Frontend)

1. **Mitgliederbereich öffnen**
2. **Shortcode-Seite aufrufen** (mit `[frontend_page_editor]`)
3. **Seite auswählen**
4. **"Mit Elementor bearbeiten" klicken**
5. **Automatischer Redirect zu Elementor**
6. **Seite bearbeiten** (60 Minuten Edit-Session)
7. **Änderungen speichern**

### Technischer Ablauf

```
1. Benutzer klickt "Mit Elementor bearbeiten"
   ↓
2. System prüft Berechtigung
   ↓
3. Nonce wird validiert
   ↓
4. Edit-Session wird gestartet (Transient + Cookie, 60 Min)
   ↓
5. Temporäre Capabilities werden vergeben:
   - edit_pages
   - edit_published_pages
   - edit_with_elementor
   ↓
6. Redirect zu Elementor Frontend Editor (/?elementor-preview={post_id})
   ↓
7. Benutzer bearbeitet Seite im Frontend
   ↓
8. Nach 60 Minuten oder Logout: Session endet, Capabilities entfernt
```

## Sicherheit

### Mehrschichtige Sicherheit

1. **Nonce-Prüfung**
   - Jeder Edit-Link hat einen eindeutigen Nonce
   - Nonce ist nur für eine Seite gültig

2. **Berechtigungsprüfung**
   - Vor jedem Zugriff wird geprüft ob User die Seite bearbeiten darf
   - Prüfung erfolgt gegen ACF User-Meta, Author-Status und Rolle

3. **Session-basierte Capabilities**
   - Capabilities werden nur temporär für 60 Minuten vergeben
   - Gespeichert in Transient + Cookie (doppelte Sicherheit)
   - Verfallen automatisch

4. **Frontend-Bearbeitung ohne wp-admin**
   - Benutzer arbeiten komplett im Frontend (/?elementor-preview={post_id})
   - Kein Zugriff auf wp-admin erforderlich
   - Elementor lädt direkt auf der Seite
   - Bestehende wp-admin-Sperren bleiben aktiv

## ACF-Felder

### User-Meta-Feld

**Feldname:** `editable_pages`
**Typ:** Post Object (Mehrfachauswahl)
**Post Type:** page
**Return Format:** ID
**Sichtbarkeit:** Nur für Administratoren im User-Profil

**Conditional Logic:**
- Feld wird nur angezeigt bei Nicht-Admin-Benutzern
- Admins sehen das Feld nicht (haben eh Vollzugriff)

## Verwendungsbeispiel

### Seite für Mitgliederbereich

Erstelle eine Seite "Meine Seiten bearbeiten":
```
[frontend_page_editor]
```

### Bedingte Anzeige mit Elementor Pro

Widget mit Dynamic Content:
```
[has_page_edit_access]
```

Conditional Logic:
- Wenn `1` → Zeige "Seiten bearbeiten"-Button
- Wenn `0` → Zeige "Keine Bearbeitungsrechte"

### In Theme/Template

```php
<?php
if ( is_user_logged_in() ) {
    $fpe = DGPTM_Frontend_Page_Editor::get_instance();
    $has_access = $fpe->user_has_page_access( get_current_user_id() );

    if ( $has_access ) {
        echo '<a href="/mitgliederbereich/seiten-bearbeiten/">Meine Seiten</a>';
    }
}
?>
```

## Styling

Das Modul verwendet modernes CSS mit:
- CSS-Variablen für einfache Anpassung
- Grid-Layout (responsive)
- Card-Design
- Elementor-Branding (Farbe: #92003b)
- Accessibility-Features
- Mobile-optimiert

### CSS-Variablen anpassen

```css
:root {
    --fpe-primary: #2492BA;        /* Hauptfarbe */
    --fpe-elementor: #92003b;      /* Elementor-Farbe */
}
```

## Technische Details

### Session-Management

**Transient:**
```php
dgptm_fpe_session_{user_id}
```

**Cookie:**
```php
dgptm_fpe_session_{user_id}
```

**Gültigkeitsdauer:** 60 Minuten

### Temporäre Capabilities

Während einer Edit-Session erhält der Benutzer:
- `edit_pages`
- `edit_published_pages`
- `edit_post`
- `read`
- `elementor`
- `edit_with_elementor`

Diese werden nach Session-Ende automatisch entfernt.

### Verzeichnisstruktur

```
frontend-page-editor/
├── css/
│   └── frontend-page-editor.css    # Styles
├── acf-json/
│   └── group_editable_pages_user.json  # ACF-Feldgruppe
├── module.json                      # Modul-Konfiguration
├── frontend-page-editor.php        # Hauptdatei
└── README.md                        # Diese Datei
```

## Troubleshooting

### Elementor öffnet sich nicht
**Lösung:** Prüfen ob Elementor installiert und aktiv ist

### Benutzer sieht keine Seiten
**Lösung:**
1. Seiten im User-Profil zuweisen
2. Oder Benutzer als Author der Seite eintragen
3. Oder Rolle auf Editor/Moderator setzen

### Session läuft ab
**Lösung:** Session läuft nach 60 Minuten ab. Benutzer muss Link erneut klicken.

### ACF-Feld wird nicht angezeigt
**Lösung:** Nur als Administrator anmelden. Feld erscheint nur bei Nicht-Admin-Benutzern.

### "Keine Berechtigung"-Fehler
**Lösung:**
1. Prüfen ob Benutzer eingeloggt ist
2. Prüfen ob Seite zugewiesen ist
3. Prüfen ob Session noch aktiv ist (60 Min)

## Support

**Voraussetzungen prüfen:**
1. ✅ Elementor installiert und aktiv?
2. ✅ ACF installiert und aktiv?
3. ✅ Seiten zugewiesen oder User ist Author?
4. ✅ Benutzer eingeloggt?

## Changelog

### Version 1.0.0 (2025-01-22)
- ✅ Initiales Release
- ✅ Direkte Elementor-Bearbeitung ohne wp-admin
- ✅ ACF User-Meta Seitenzuweisung
- ✅ Rollenbasierte Berechtigungen
- ✅ Session-Management mit Transients
- ✅ Temporäre Capabilities
- ✅ Shortcodes für Liste und Zugriffsprüfung
- ✅ Modernes Responsive Design
