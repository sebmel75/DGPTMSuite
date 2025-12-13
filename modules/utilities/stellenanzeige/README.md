# Stellenanzeigen Manager

**Version:** 2.0.0
**Kategorie:** Utilities
**Autor:** Sebastian Melzer

## Beschreibung

Vollständiges Stellenanzeigen-Verwaltungssystem mit Frontend-Editor, ACF-Integration und modernem Design.

## Features

✅ Custom Post Type "Stellenanzeige"
✅ Frontend-Editor für Benutzer
✅ ACF-Feldgruppen (automatisch geladen)
✅ Modernes, responsives CSS-Design
✅ Bildupload-Unterstützung
✅ Gültigkeitsdatum mit automatischer Filterung
✅ Benutzer-Berechtigungssystem
✅ DSGVO-konform (keine externen Ressourcen)

## Abhängigkeiten

**Erforderlich:**
- Advanced Custom Fields (ACF) Plugin

**WordPress:**
- WordPress 5.8+
- PHP 7.4+

## Installation

1. Modul aktivieren im DGPTM Suite Dashboard
2. ACF Plugin muss installiert und aktiv sein
3. Zwei WordPress-Seiten erstellen:
   - Seite 1: `[stellenanzeigen_editor]` - für Verwaltung
   - Seite 2: `[stellenanzeige_bearbeiten]` - für Bearbeitung
4. Seiten in **Einstellungen → Stellenanzeigen Manager** zuweisen

## Shortcodes

### 1. `[stellenanzeigen]`
Zeigt alle gültigen Stellenanzeigen an.

**Verwendung:**
```
[stellenanzeigen]
```

**Ausgabe:**
- Liste aller veröffentlichten, gültigen Stellenanzeigen
- Mit Bild, Beschreibung, Arbeitgeber
- "Jetzt bewerben"-Button mit externem Link

---

### 2. `[stellenanzeigen_editor]`
Frontend-Editor zum Erstellen und Verwalten von Stellenanzeigen.

**Verwendung:**
```
[stellenanzeigen_editor]
```

**Funktionen:**
- Neue Stellenanzeige erstellen
- Tabellarische Übersicht aller Stellenanzeigen
- Links zur Bearbeitung

**Zugriff:** Nur für Benutzer mit `edit_stellenanzeigen` Capability

---

### 3. `[stellenanzeige_bearbeiten]`
Bearbeitungsformular für einzelne Stellenanzeige.

**Verwendung:**
```
[stellenanzeige_bearbeiten]
```

**Parameter:** Über URL: `?staz_id=123`

**Funktionen:**
- Stellenanzeige bearbeiten
- Stellenanzeige löschen
- Hochschieben (Datum aktualisieren)
- Status umschalten (publish/draft)

## ACF-Felder

Das Modul verwendet folgende ACF-Felder:

| Feld | Typ | Pflicht | Beschreibung |
|------|-----|---------|--------------|
| `link_zur_stellenanzeige` | URL | Nein | Bewerbungs-URL |
| `arbeitgeber` | Text | Nein | Name des Arbeitgebers |
| `stellenbeschreibung` | Textarea | Nein | Stellenbeschreibung |
| `bild_zur_stellenanzeige` | Image | Nein | Stellenanzeigen-Bild |
| `gultig_bis` | Date | **Ja** | Gültigkeitsdatum |

**Hinweis:** ACF-Felder werden automatisch aus `acf-json/` geladen.

## Berechtigungen

### ACF-User-Meta-Feld (Toggle)

Die Berechtigung zur Stellenanzeigen-Verwaltung wird über ein **ACF-User-Meta-Feld** gesteuert:

**Feldname:** `stellenanzeigen_anlegen`
**Typ:** True/False (Toggle)
**Speicherort:** User-Meta
**Feld-Gruppe:** "Stellenanzeigen - Berechtigung"

### Zugriffskontrolle

**Administratoren:**
- Haben immer vollen Zugriff
- Können alle Stellenanzeigen bearbeiten
- WordPress Capabilities werden automatisch gesetzt

**Normale Benutzer:**
- Benötigen das aktivierte Toggle-Feld `stellenanzeigen_anlegen = true`
- Können dann eigene Stellenanzeigen erstellen und verwalten
- Zugriff wird in jedem Shortcode geprüft

### Berechtigung aktivieren

1. **WordPress Admin → Benutzer**
2. **Benutzer bearbeiten**
3. **Feld "Stellenanzeigen anlegen"** auf **"Ja"** setzen
4. **Aktualisieren**

Der Benutzer kann nun die Frontend-Formulare verwenden.

## Verwendungsbeispiel

### Seite für Stellenanzeigen
Erstelle eine Seite "Stellenanzeigen" mit:
```
[stellenanzeigen]
```

### Seite für Verwaltung
Erstelle eine Seite "Stellenanzeigen verwalten" mit:
```
[stellenanzeigen_editor]
```

### Seite für Bearbeitung
Erstelle eine Seite "Stellenanzeige bearbeiten" mit:
```
[stellenanzeige_bearbeiten]
```

### Einstellungen konfigurieren
1. WordPress Admin → **Einstellungen → Stellenanzeigen Manager**
2. Seite für Editor auswählen
3. Seite für Bearbeitung auswählen
4. Speichern

## Workflow

1. **Benutzer erstellt Stellenanzeige:**
   - Öffnet Editor-Seite
   - Füllt Formular aus (Titel, Beschreibung, Link, etc.)
   - Lädt optional ein Bild hoch
   - Setzt Gültigkeitsdatum
   - Klickt "Stellenanzeige erstellen"
   - Wird zur Bearbeitungsseite weitergeleitet

2. **Stellenanzeige bearbeiten:**
   - In Tabelle auf "Bearbeiten" klicken
   - Felder anpassen
   - "Änderungen speichern"

3. **Stellenanzeige hochschieben:**
   - Aktualisiert das Veröffentlichungsdatum auf "jetzt"
   - Sortiert Stellenanzeige nach oben

4. **Status umschalten:**
   - Publish → Draft (verstecken)
   - Draft → Publish (veröffentlichen)

## Styling

Das Modul verwendet modernes CSS mit:
- CSS-Variablen für einfache Anpassung
- Vollständig responsive (Mobile-First)
- Accessibility-Features (Focus-States, ARIA)
- Print-Styles
- Dark Mode kompatibel
- Reduced Motion Support

### CSS-Variablen anpassen

```css
:root {
    --staz-primary: #2492BA;        /* Hauptfarbe */
    --staz-primary-hover: #005792;  /* Hover-Farbe */
    --staz-accent: #BD1722;         /* Akzentfarbe */
}
```

## Technische Details

### Verzeichnisstruktur
```
stellenanzeige/
├── css/
│   └── dgptm-staz-styles.css     # Hauptstyles
├── acf-json/
│   └── group_679128203084b.json  # ACF-Feldgruppe
├── module.json                    # Modul-Konfiguration
├── stellenanzeige.php            # Hauptdatei
└── README.md                      # Diese Datei
```

### Datenbankstruktur

**Post Type:** `stellenanzeige`
**Custom Fields:** Gespeichert als Post Meta via ACF

### Hooks & Filter

**Actions:**
- `init` - Registrierung Post Type
- `admin_init` - Registrierung Einstellungen
- `admin_menu` - Admin-Menü hinzufügen
- `wp_enqueue_scripts` - CSS laden

**Filter:**
- `acf/settings/load_json` - ACF-JSON-Pfad hinzufügen

## Troubleshooting

### Felder werden nicht angezeigt
**Lösung:** ACF Plugin aktivieren und Seite neu laden

### CSS wird nicht geladen
**Lösung:** Browser-Cache leeren oder Permalink-Struktur neu speichern

### Berechtigungsfehler
**Lösung:** Modul deaktivieren und neu aktivieren (setzt Capabilities neu)

### Activation Hooks funktionieren nicht
**Problem:** `register_activation_hook()` funktioniert nicht in Modulen
**Lösung:** Capabilities werden beim ersten Laden automatisch gesetzt

## Sicherheit

- ✅ Nonce-Prüfung bei allen Formular-Aktionen
- ✅ Capability-Checks für alle Operationen
- ✅ Input-Sanitization mit WordPress-Funktionen
- ✅ Output-Escaping (esc_html, esc_url, etc.)
- ✅ ABSPATH-Prüfung verhindert direkten Zugriff
- ✅ File-Upload mit Typ-Validierung

## Support

Bei Problemen:
1. ACF Plugin installiert und aktiv?
2. WordPress 5.8+ und PHP 7.4+?
3. Seiten korrekt in Einstellungen zugewiesen?
4. Benutzer hat richtige Capabilities?

## Changelog

### Version 2.0.0 (2025-01-22)
- ✅ Modernes CSS-Design hinzugefügt
- ✅ ACF-JSON-Integration
- ✅ ACF-Dependency deklariert
- ✅ wp_safe_redirect() statt wp_redirect()
- ✅ Responsive Design
- ✅ Accessibility-Verbesserungen
- ✅ Print-Styles

### Version 1.0.0
- Initiales Release
