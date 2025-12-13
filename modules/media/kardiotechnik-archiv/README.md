# Kardiotechnik Archiv - WordPress Plugin

Ein WordPress-Plugin zur Verwaltung und Durchsuchung des Kardiotechnik-Zeitschriftenarchivs.

## Funktionen

- **PDF-Verwaltung**: Importieren Sie automatisch PDFs aus Verzeichnissen oder laden Sie einzelne Artikel manuell hoch
- **Durchsuchbares Archiv**: Volltextsuche in Titeln, Autoren, Schlagwörtern und Zusammenfassungen
- **Zeitraumfilter**: Filtern Sie Artikel nach Erscheinungsjahr
- **Metadaten-Verwaltung**: Erfassen Sie Details wie Autor, Schlagwörter, Zusammenfassung und Seitenzahlen
- **Responsives Design**: Optimiert für Desktop und mobile Geräte
- **Shortcode-Integration**: Einfache Einbindung auf jeder WordPress-Seite

## Installation

### Schritt 1: Plugin-Verzeichnis erstellen

1. Erstellen Sie einen Ordner mit dem Namen `kardiotechnik-archiv` im WordPress-Plugin-Verzeichnis:
   ```
   wp-content/plugins/kardiotechnik-archiv/
   ```

2. Kopieren Sie alle Plugin-Dateien in diesen Ordner:
   ```
   kardiotechnik-archiv/
   ├── kardiotechnik-archiv.php
   ├── README.md
   ├── assets/
   │   ├── admin.css
   │   ├── admin.js
   │   ├── frontend.css
   │   └── frontend.js
   └── templates/
       ├── admin-articles.php
       ├── admin-import.php
       └── frontend-archive.php
   ```

### Schritt 2: Plugin aktivieren

1. Melden Sie sich im WordPress-Admin-Bereich an
2. Navigieren Sie zu **Plugins** → **Installierte Plugins**
3. Suchen Sie nach "Kardiotechnik Archiv"
4. Klicken Sie auf **Aktivieren**

Bei der Aktivierung wird automatisch eine Datenbanktabelle `wp_kardiotechnik_articles` erstellt.

### Schritt 3: PDFs importieren

#### Automatischer Import aus Verzeichnissen

1. Gehen Sie zu **Kardiotechnik** → **Import** im Admin-Menü
2. Geben Sie den vollständigen Pfad zum Archiv-Verzeichnis ein, z.B.:
   ```
   C:\Users\SebastianMelzer\...\Archiv Kardiotechnik ab 1975
   ```
3. Klicken Sie auf **Import starten**

Das Plugin importiert alle PDFs aus Verzeichnissen, die dem Schema `YYYY-MM Kardiotechnik` folgen.

#### Manuelles Hinzufügen einzelner Artikel

1. Gehen Sie zu **Kardiotechnik** → **Import**
2. Scrollen Sie zum Formular "Einzelnen Artikel hinzufügen"
3. Füllen Sie die Artikeldetails aus:
   - Jahr (z.B. 2005)
   - Ausgabe (z.B. 01, 02, S01)
   - Titel
   - Autor(en)
   - Schlagwörter (komma-getrennt)
   - Zusammenfassung
   - Seitenzahlen
   - PDF-Datei
4. Klicken Sie auf **Artikel hinzufügen**

### Schritt 4: Archiv auf einer Seite einbinden

1. Erstellen oder bearbeiten Sie eine WordPress-Seite
2. Fügen Sie den folgenden Shortcode ein:
   ```
   [kardiotechnik_archiv]
   ```

Optional können Sie den Zeitraum einschränken:
```
[kardiotechnik_archiv year_from="2000" year_to="2024"]
```

## Verwendung

### Artikel verwalten

Unter **Kardiotechnik** → **Artikel** können Sie:
- Alle importierten Artikel anzeigen
- Artikel durchsuchen
- Artikeldetails bearbeiten (Titel, Autor, Schlagwörter, etc.)
- PDFs anzeigen
- Artikel löschen

### Frontend-Suche

Besucher können auf der Frontend-Seite:
- Nach Suchbegriffen suchen (durchsucht Titel, Autor, Schlagwörter und Zusammenfassung)
- Nach Zeitraum filtern (von Jahr bis Jahr)
- Ergebnisse sortieren (nach Jahr, Titel oder Autor)
- PDFs direkt im Browser öffnen

## Verzeichnisstruktur

Das Plugin erwartet PDF-Verzeichnisse im folgenden Format:

```
Archiv Kardiotechnik ab 1975/
├── 1975-01 Kardiotechnik/
│   └── 1975-01 Kardiotechnik.pdf
├── 1976-01 Kardiotechnik/
│   └── 1976-01 Kardiotechnik.pdf
├── 2005-S01 Kardiotechnik/     (Sonderhefte mit "S")
│   └── 2005-S01.pdf
└── ...
```

### Benennungskonventionen

- **Reguläre Ausgaben**: `YYYY-MM Kardiotechnik` (z.B. `2005-01 Kardiotechnik`)
- **Sonderhefte**: `YYYY-SMM Kardiotechnik` (z.B. `2005-S01 Kardiotechnik`)

## Datenbank-Schema

Die Plugin-Tabelle `wp_kardiotechnik_articles` enthält:

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| id | mediumint(9) | Primärschlüssel |
| year | int(4) | Erscheinungsjahr |
| issue | varchar(10) | Ausgabe (z.B. "01", "S01") |
| title | varchar(255) | Artikeltitel |
| author | varchar(255) | Autor(en) |
| keywords | text | Schlagwörter |
| abstract | text | Zusammenfassung |
| page_start | int(5) | Startseite |
| page_end | int(5) | Endseite |
| pdf_path | varchar(500) | Dateipfad zur PDF |
| pdf_url | varchar(500) | URL zur PDF |
| created_at | datetime | Erstellungsdatum |

## Sicherheit

Das Plugin implementiert folgende Sicherheitsmaßnahmen:

- **Nonce-Validierung**: Alle AJAX-Anfragen werden mit Nonces abgesichert
- **Berechtigungsprüfungen**: Admin-Funktionen erfordern `manage_options`-Berechtigung
- **Input-Sanitization**: Alle Benutzereingaben werden bereinigt
- **Prepared Statements**: SQL-Injection-Schutz durch prepared statements
- **Direktzugriff-Schutz**: Plugin-Dateien können nicht direkt aufgerufen werden

## Anpassungen

### CSS-Styling anpassen

Bearbeiten Sie `assets/frontend.css` für Frontend-Anpassungen oder `assets/admin.css` für Admin-Anpassungen.

### Templates anpassen

Die Templates befinden sich im Ordner `templates/`:
- `frontend-archive.php` - Frontend-Archiv-Anzeige
- `admin-articles.php` - Artikel-Verwaltung
- `admin-import.php` - Import-Seite

## Systemanforderungen

- WordPress 5.0 oder höher
- PHP 7.4 oder höher
- MySQL 5.7 oder höher mit InnoDB und FULLTEXT-Unterstützung

## Support und Entwicklung

Entwickelt für die Deutsche Gesellschaft für Perfusiologie und Technische Medizin e.V. (DGPTM)

**Version**: 1.0.0
**Lizenz**: GPL2

## Fehlerbehebung

### Import funktioniert nicht

- Stellen Sie sicher, dass der Pfad zum Verzeichnis korrekt ist
- Prüfen Sie, ob die Verzeichnisse dem Namensschema entsprechen
- Überprüfen Sie die Dateiberechtigungen

### PDFs werden nicht angezeigt

- Stellen Sie sicher, dass das Upload-Verzeichnis beschreibbar ist
- Prüfen Sie die WordPress-Upload-Einstellungen

### Suche liefert keine Ergebnisse

- Stellen Sie sicher, dass Artikel importiert wurden
- Prüfen Sie, ob der Suchbegriff in den Artikeln vorkommt
- Überprüfen Sie die Zeitraumfilter

## Änderungsprotokoll

### Version 1.0.0 (2025)
- Initiale Veröffentlichung
- PDF-Import aus Verzeichnissen
- Volltextsuche
- Metadaten-Verwaltung
- Responsive Frontend
- Admin-Bereich für Verwaltung
