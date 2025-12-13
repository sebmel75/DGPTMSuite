<?php
/**
 * Erstelle fehlende Module-Guides
 */

$guides_dir = __DIR__ . '/guides/';

// Guide-Daten für fehlende Module
$missing_guides = [
    'acf-permissions-manager' => [
        'title' => 'ACF Permissions Manager',
        'description' => 'Verwaltet und überwacht ACF-Berechtigungen für Benutzer mit Batch-Zuweisung, Matrix-Ansicht und CSV-Export.',
        'content' => "# ACF Permissions Manager

## Übersicht

Der ACF Permissions Manager ermöglicht die zentrale Verwaltung von Advanced Custom Fields (ACF) Berechtigungen für Benutzer. Das Modul bietet eine übersichtliche Matrix-Ansicht, Batch-Operationen und CSV-Export-Funktionen.

## Hauptfunktionen

- **Matrix-Ansicht**: Alle Benutzer × Alle Berechtigungen auf einen Blick
- **Batch-Zuweisung**: Mehrere Berechtigungen gleichzeitig vergeben/entziehen
- **Dynamische Permission-Erkennung**: Erkennt automatisch alle `true_false` ACF-Felder aus der Berechtigungsgruppe
- **CSV-Export**: Exportieren Sie Berechtigungsmatrizen
- **4-Tab-Interface**: Übersicht, Nach Berechtigung, Nach Benutzer, Batch-Operationen
- **Toggle-Switches**: Moderne UI mit direktem Speichern

## Admin-Interface

**Menü:** Benutzer → ACF Permissions

### Tab 1: Übersicht (Matrix)

Zeigt alle Benutzer und ihre Berechtigungen in einer Tabelle:
- **Zeilen**: Benutzer (Name, E-Mail, Rolle)
- **Spalten**: Berechtigungen (dynamisch erkannt)
- **Zellen**: Toggle-Switches (AN/AUS)
- **Aktionen**: Direktes Speichern per Click

### Tab 2: Nach Berechtigung

Ansicht nach Berechtigung sortiert:
- Wählen Sie eine Berechtigung aus
- Sehen Sie alle Benutzer mit dieser Berechtigung
- Berechtigung für mehrere Benutzer gleichzeitig vergeben/entziehen

### Tab 3: Nach Benutzer

Ansicht nach Benutzer sortiert:
- Wählen Sie einen Benutzer aus
- Sehen Sie alle Berechtigungen des Benutzers
- Mehrere Berechtigungen gleichzeitig ändern

### Tab 4: Batch-Operationen

Massenoperationen:
- **Batch-Zuweisung**: Mehreren Benutzern mehrere Berechtigungen zuweisen
- **Batch-Entzug**: Mehreren Benutzern Berechtigungen entziehen
- **Nach Rolle filtern**: Nur Benutzer einer bestimmten Rolle bearbeiten

## Verwendung

### Berechtigung einzeln setzen

1. Tab **Übersicht** öffnen
2. Benutzer in Tabelle finden
3. Toggle-Switch bei gewünschter Berechtigung klicken
4. Automatisches Speichern

### Batch-Zuweisung

1. Tab **Batch-Operationen** öffnen
2. Rolle auswählen (optional)
3. Benutzer auswählen (Checkboxen)
4. Berechtigungen auswählen
5. \"Zuweisen\" klicken

### CSV-Export

1. Tab **Übersicht**
2. Button \"CSV-Export\" klicken
3. Datei wird heruntergeladen
4. Format: Benutzer, E-Mail, Rolle, Permission1, Permission2, ...

## ACF-Integration

Das Modul erkennt automatisch alle `true_false` Felder aus der ACF Field Group:

```php
// Standard-Gruppe-Key
'group_dgptm_permissions'
```

**Erkannte Felder:**
- Alle Felder vom Typ `true_false`
- Field Name wird als Permission-ID verwendet
- Field Label wird als Anzeigename verwendet

### Eigene Berechtigungen hinzufügen

1. ACF → Field Groups → DGPTM Permissions öffnen
2. Neues `True / False` Feld hinzufügen
3. Field Name: z.B. `can_access_reports`
4. Field Label: z.B. \"Zugriff auf Berichte\"
5. Speichern → Modul erkennt automatisch

## AJAX-Operationen

Alle Toggle-Aktionen verwenden AJAX:
- Kein Neuladen der Seite
- Visuelles Feedback (Spinner)
- Fehlerbehandlung
- Optimistic UI Updates

## Technische Details

- **ACF Field Group Key**: `group_dgptm_permissions`
- **User Meta**: Berechtigungen werden als ACF User Fields gespeichert
- **Nonce Security**: Alle AJAX-Calls mit Nonce-Validierung
- **Capability Check**: `manage_options` erforderlich

## Tipps & Best Practices

1. **Strukturierte Berechtigungen**: Verwenden Sie klare Field Names (z.B. `can_edit_content`)
2. **Gruppenlogik**: Nutzen Sie Rollen-Filter für schnelle Batch-Zuweisungen
3. **Regelmäßige Exports**: Erstellen Sie CSV-Backups der Berechtigungen
4. **Testing**: Testen Sie neue Berechtigungen mit Test-Benutzer

## Fehlerbehebung

### Berechtigungen erscheinen nicht

**Ursache**: ACF Field Group nicht korrekt konfiguriert

**Lösung**:
1. Prüfen Sie, ob Field Group existiert
2. Stellen Sie sicher, dass Felder vom Typ `true_false` sind
3. Field Group Key muss `group_dgptm_permissions` sein

### Toggle speichert nicht

**Ursache**: JavaScript-Fehler oder Berechtigung fehlt

**Lösung**:
1. Browser-Console prüfen
2. Stelle sicher, dass Benutzer `manage_options` Capability hat
3. Cache leeren

## Support

Bei Problemen:
- WordPress Debug-Log prüfen
- Browser-Console auf JavaScript-Fehler prüfen
- ACF Field Group validieren",
        'features' => [
            'Matrix-Ansicht aller Benutzer und Berechtigungen',
            'Dynamische Permission-Erkennung aus ACF',
            'Toggle-Switches mit direktem Speichern',
            'Batch-Zuweisung und -Entzug',
            '4-Tab-Interface',
            'CSV-Export',
            'Nach Rolle filtern'
        ],
        'keywords' => ['acf', 'permissions', 'benutzer', 'rechte', 'batch', 'matrix', 'export']
    ],

'fortbildung' => [
        'title' => 'Fortbildungsverwaltung',
        'description' => 'Zentrale Verwaltung von Fortbildungsnachweisen mit automatischer Zertifikatserstellung und Integration mit Quiz und Webinaren.',
        'content' => "# Fortbildungsverwaltung

## Übersicht

Das Fortbildungsmodul ist die zentrale Komponente für die Verwaltung von Fortbildungsnachweisen, CME-Punkten und Zertifikaten. Es integriert sich nahtlos mit dem Quiz-Manager und Vimeo-Webinaren.

## Hauptfunktionen

- **Automatische Fortbildungserstellung**: Durch Quiz-Completion oder Webinar-Viewing
- **PDF-Zertifikatsgenerierung**: Mit FPDF-Integration
- **CME-Punkte-Tracking**: Automatische Berechnung und Verwaltung
- **Dubletten-Prävention**: Verhindert doppelte Einträge
- **ACF-Integration**: Strukturierte Datenverwaltung
- **E-Mail-Versand**: Automatischer Versand von Zertifikaten
- **Admin-Interface**: Übersicht und Verwaltung aller Fortbildungen

## Custom Post Type

**Post Type**: `fortbildung`

### ACF-Felder

- `user_id` - WordPress Benutzer-ID
- `datum` - Datum der Fortbildung
- `ort` - Ort/Veranstaltung
- `typ` - Typ (Quiz, Webinar, Präsenz)
- `punkte` - CME-Punkte
- `vnr` - Veranstaltungsnummer
- `token` - Eindeutiger Token
- `freigegeben` - Status (Ja/Nein)
- `pdf_path` - Pfad zum PDF-Zertifikat

## Automatische Integration

### Mit Quiz-Manager

Nach Quiz-Completion:
1. Benutzer schließt Quiz erfolgreich ab
2. Quiz-Manager ruft `create_fortbildung_entry()` auf
3. Fortbildung wird automatisch erstellt
4. PDF wird generiert
5. E-Mail wird versendet

### Mit Vimeo-Webinare

Nach Webinar-Viewing:
1. Benutzer sieht Webinar komplett
2. Vimeo-Modul triggert Fortbildungserstellung
3. Gleicher Ablauf wie bei Quiz

## PDF-Zertifikate

Das Modul generiert professionelle PDF-Zertifikate mit:
- DGPTM-Logo
- Benutzer-Name
- Fortbildungstitel
- CME-Punkte
- VNR (falls vorhanden)
- Datum und Ort
- Unterschrift (optional)
- QR-Code für Verifizierung

### FPDF-Integration

```php
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);
// ... PDF-Generierung
$pdf->Output('F', $pdf_path);
```

## Admin-Interface

**Menü**: Fortbildungen

### Übersicht

Liste aller Fortbildungen:
- Benutzer
- Datum
- Typ
- Punkte
- Status
- Aktionen (Ansehen, PDF herunterladen, Löschen)

### Filtern

- Nach Benutzer
- Nach Typ
- Nach Status
- Nach Datum

## E-Mail-Versand

Automatischer Versand nach Erstellung:

**Betreff**: \"Ihr Fortbildungszertifikat\"

**Inhalt**:
- Glückwunsch-Text
- Fortbildungsdetails
- PDF als Anhang
- Link zum Download

### Konfiguration

E-Mail-Template anpassen:
```php
add_filter('dgptm_fortbildung_email_template', function($template, $data) {
    $template['subject'] = 'Ihre Fortbildung: ' . $data['titel'];
    $template['body'] = 'Sehr geehrte/r ' . $data['username'] . ',...';
    return $template;
}, 10, 2);
```

## Dubletten-Prävention

Das Modul verhindert doppelte Einträge durch:
- Meta-Query-Checks (user_id + typ + datum)
- Token-basierte Validierung
- Logging bei Dubletten-Versuchen

## Programmatische Verwendung

### Fortbildung erstellen

```php
// Manuelle Erstellung
$fortbildung_id = create_fortbildung_entry([
    'user_id' => 123,
    'title' => 'Kardiologie Update 2024',
    'punkte' => 8,
    'typ' => 'Webinar',
    'ort' => 'Online',
    'vnr' => '2024-001',
    'datum' => '2024-11-29',
]);
```

### Fortbildungen eines Benutzers abrufen

```php
$args = [
    'post_type' => 'fortbildung',
    'meta_query' => [
        [
            'key' => 'user_id',
            'value' => get_current_user_id(),
        ]
    ]
];
$fortbildungen = get_posts($args);
```

## Hooks & Filter

### Actions

- `dgptm_fortbildung_created` - Nach Erstellung
- `dgptm_fortbildung_pdf_generated` - Nach PDF-Generierung
- `dgptm_fortbildung_email_sent` - Nach E-Mail-Versand

### Filter

- `dgptm_fortbildung_email_template` - E-Mail-Template anpassen
- `dgptm_fortbildung_pdf_template` - PDF-Layout anpassen
- `dgptm_fortbildung_cme_punkte` - Punkteberechnung anpassen

## Shortcodes

### [meine_fortbildungen]

Zeigt Fortbildungen des eingeloggten Benutzers:

```
[meine_fortbildungen limit=\"10\"]
```

**Parameter**:
- `limit` - Anzahl (Default: alle)
- `typ` - Filter nach Typ
- `show_download` - Download-Link anzeigen (yes/no)

## Frontend-Anzeige

Standardmäßig werden Fortbildungen nicht im Frontend angezeigt (nur im Admin). Für Frontend-Ansicht:

```php
// In functions.php
add_action('init', function() {
    $args = get_post_type_object('fortbildung');
    $args->public = true;
    $args->publicly_queryable = true;
    register_post_type('fortbildung', (array) $args);
});
```

## CME-Punkte-Berechnung

Punkte werden automatisch zugewiesen:
- Quiz: Basierend auf Quiz-Konfiguration
- Webinar: Basierend auf Webinar-Länge
- Manuell: Admin kann Punkte manuell setzen

**Berechnung Webinar**:
```
Punkte = Dauer in Minuten / 45
(Aufgerundet)
```

## Technische Details

- **Post Type**: `fortbildung`
- **PDF-Library**: FPDF
- **Storage**: `wp-content/uploads/fortbildungen/`
- **Metafelder**: ACF
- **Encoding**: UTF-8
- **File Permission**: 0644

## Tipps & Best Practices

1. **Backup**: Sichern Sie PDF-Verzeichnis regelmäßig
2. **E-Mail**: Testen Sie E-Mail-Versand mit Test-Benutzer
3. **VNR**: Verwenden Sie konsistentes VNR-Format
4. **Archivierung**: Alte Fortbildungen archivieren statt löschen
5. **Datenschutz**: PDFs enthalten personenbezogene Daten

## Fehlerbehebung

### PDF wird nicht generiert

**Ursache**: FPDF-Library fehlt oder Verzeichnis nicht beschreibbar

**Lösung**:
1. Prüfen ob `/libraries/fpdf/` existiert
2. Upload-Verzeichnis Rechte prüfen (775)
3. PHP Memory Limit erhöhen (128MB+)

### E-Mail kommt nicht an

**Lösung**:
1. WordPress SMTP-Plugin installieren
2. E-Mail-Logs prüfen
3. Spam-Ordner kontrollieren

### Dubletten trotz Prävention

**Ursache**: Race Condition bei simultanen Requests

**Lösung**:
- Implementieren Sie zusätzliche Locks
- Erhöhen Sie Query-Präzision",
        'features' => [
            'Automatische Fortbildungserstellung',
            'PDF-Zertifikatsgenerierung',
            'CME-Punkte-Tracking',
            'E-Mail-Versand',
            'Dubletten-Prävention',
            'Quiz-Integration',
            'Webinar-Integration',
            'Admin-Interface'
        ],
        'keywords' => ['fortbildung', 'cme', 'zertifikat', 'pdf', 'quiz', 'webinar', 'punkte']
    ],

    'vimeo-webinare' => [
        'title' => 'Vimeo Webinare',
        'description' => 'Vimeo-Videos als Webinare mit dynamischen URLs, zeitbasiertem Progress-Tracking und automatischer Zertifikatsgenerierung.',
        'content' => "# Vimeo Webinare

## Übersicht

Das Vimeo-Webinare Modul ermöglicht die Bereitstellung von Vimeo-Videos als strukturierte Webinare mit automatischem Fortbildungspunkte-System, Zertifikatsgenerierung und Batch-Import-Funktion.

## Hauptfunktionen

- **Dynamische URLs**: `/wissen/webinar/{id}` oder `?webinar={id}`
- **Zeitbasiertes Tracking**: Verhindert Skip-Forward-Cheating
- **Automatische Zertifikate**: PDF-Generierung nach Completion
- **Batch-Import**: Import ganzer Vimeo-Ordner
- **E-Mail-Benachrichtigungen**: Anpassbare Templates
- **Frontend-Manager**: Benutzer können eigene Webinare verwalten
- **Progress-Tracking**: Speichert Fortschritt pro Benutzer
- **Duplicate Detection**: Verhindert doppelte Fortbildungseinträge

## Custom Post Type

**Post Type**: `webinar`

### ACF-Felder

- `vimeo_video_id` - Vimeo Video ID
- `vimeo_duration` - Dauer in Sekunden
- `cme_punkte` - CME-Punkte
- `vnr` - Veranstaltungsnummer
- `completion_threshold` - Schwellenwert (z.B. 95%)
- `email_template` - E-Mail-Vorlage

## Dynamisches Routing

Das Modul erstellt automatisch URLs:

**Format 1**: `https://domain.de/wissen/webinar/123`
**Format 2**: `https://domain.de?webinar=123`

### Rewrite Rules

```php
add_rewrite_rule(
    '^wissen/webinar/([0-9]+)/?$',
    'index.php?webinar=$matches[1]',
    'top'
);
```

**WICHTIG**: Nach Aktivierung Permalinks neu speichern!

## Zeitbasiertes Tracking

Um Skip-Forward zu verhindern:
- Tracking basiert auf **tatsächlich angesehener Zeit**
- Nicht auf Video-Position
- Serverseitiges Logging
- Client sendet Ping alle 10 Sekunden

### Tracking-Flow

1. Benutzer startet Video
2. JavaScript sendet Progress-Updates
3. Server loggt kumulierte Zeit
4. Bei Erreichen des Schwellenwerts: Completion
5. Automatische Fortbildungserstellung

## Video-Player

**Vimeo Player API Integration**:
- Responsive iFrame-Embed
- Custom Controls
- Fullscreen Support
- Quality Selection
- Speed Control

### JavaScript Tracking

```javascript
player.on('timeupdate', function(data) {
    // Alle 10 Sekunden
    if (Math.floor(data.seconds) % 10 === 0) {
        sendProgressUpdate(data.seconds);
    }
});
```

## Batch-Import

### Vimeo-Ordner importieren

1. Admin → Webinare → Batch Import
2. Vimeo Access Token eingeben
3. Folder ID eingeben
4. Import starten

**Was wird importiert**:
- Titel
- Beschreibung
- Dauer
- Thumbnail
- Video-ID

**Duplicate Detection**:
- Prüft auf existierende Vimeo-ID
- Überschreibt optional
- Logging aller Aktionen

### Vimeo API Setup

1. Vimeo Developer Account erstellen
2. App erstellen
3. Access Token generieren mit Scope: `video`
4. Token in Modul-Einstellungen eingeben

## Completion & Zertifikate

### Completion-Trigger

Erfolgt automatisch wenn:
- Schwellenwert erreicht (z.B. 95%)
- Benutzer eingeloggt
- Kein Duplikat existiert

### Zertifikat-Generierung

Nach Completion:
1. Fortbildungseintrag erstellen (via Fortbildungs-Modul)
2. PDF-Zertifikat generieren (FPDF)
3. E-Mail versenden mit Anhang
4. Erfolgsseite anzeigen

### E-Mail-Template

Anpassbar per ACF-Feld im Webinar:

```
Betreff: Zertifikat für {webinar_title}

Sehr geehrte/r {user_name},

herzlichen Glückwunsch! Sie haben das Webinar
\"{webinar_title}\" erfolgreich abgeschlossen.

Sie erhalten {cme_punkte} CME-Punkte.

Ihr Zertifikat finden Sie im Anhang.

Mit freundlichen Grüßen,
DGPTM
```

**Platzhalter**:
- `{webinar_title}`
- `{user_name}`
- `{user_email}`
- `{cme_punkte}`
- `{vnr}`
- `{completion_date}`

## Admin-Interface

**Menü**: Webinare

### Funktionen

1. **Webinar hinzufügen/bearbeiten**
   - Vimeo Video ID
   - Titel & Beschreibung
   - CME-Punkte konfigurieren
   - VNR eingeben
   - E-Mail-Template anpassen

2. **Batch-Import**
   - Vimeo Access Token
   - Folder ID
   - Import starten
   - Status-Log

3. **Progress-Tracking**
   - Ansicht aller User-Progress-Daten
   - Filter nach Benutzer/Webinar
   - Completion-Status

4. **Einstellungen**
   - Vimeo API Token
   - Standard-Template
   - Completion-Threshold
   - E-Mail-Einstellungen

## Frontend-Manager

Berechtigte Benutzer können:
- Eigene Webinare erstellen
- Videos hochladen (Vimeo)
- Metadaten bearbeiten
- Statistiken einsehen

**Zugriff**: ACF-Berechtigung `can_manage_webinars`

## Shortcodes

### [webinar_player]

Bettet Webinar-Player ein:

```
[webinar_player id=\"123\"]
```

### [meine_webinare]

Liste der Webinare des Benutzers:

```
[meine_webinare status=\"completed\"]
```

**Parameter**:
- `status` - all, completed, in_progress
- `limit` - Anzahl

### [webinar_katalog]

Alle verfügbaren Webinare:

```
[webinar_katalog category=\"kardiologie\"]
```

## Technische Details

- **Vimeo API**: v3.4
- **Player**: Vimeo Player SDK
- **Tracking-Interval**: 10 Sekunden
- **Completion-Threshold**: 95% (konfigurierbar)
- **DB-Table**: `wp_postmeta` (Progress als Meta)
- **PDF-Library**: FPDF (shared)

## Debugging

Ausführliches Logging (seit v1.2.4):

```
[VW] Create Fortbildung - Start
[VW] Create Fortbildung - User: 123, Webinar: 456
[VW] Create Fortbildung - Post created: 789
[VW] Create Fortbildung - PDF generated
[VW] Create Fortbildung - Email sent
[VW] Create Fortbildung - SUCCESS!
```

**Log-Location**: `wp-content/debug.log`

## Hooks & Filter

### Actions

- `vw_webinar_started` - Wenn Benutzer Video startet
- `vw_webinar_completed` - Bei Completion
- `vw_fortbildung_created` - Nach Fortbildungserstellung

### Filter

- `vw_completion_threshold` - Schwellenwert anpassen
- `vw_email_template` - E-Mail-Template filtern
- `vw_cme_punkte_calculation` - Punkteberechnung

## Tipps & Best Practices

1. **Vimeo Privacy**: Videos auf \"Unlisted\" oder \"Private\" setzen
2. **Permalinks**: Nach Aktivierung neu speichern!
3. **Testing**: Mit Test-Benutzer komplett durchspielen
4. **E-Mail**: SMTP-Plugin für zuverlässigen Versand
5. **Monitoring**: Debug-Log regelmäßig prüfen

## Fehlerbehebung

### Video lädt nicht

**Ursache**: Falsche Vimeo-ID oder Privacy-Einstellungen

**Lösung**:
1. Vimeo-ID prüfen (nur Zahlen)
2. Video auf \"Unlisted\" setzen
3. Embed-Rechte in Vimeo aktivieren

### Progress wird nicht gespeichert

**Ursache**: JavaScript-Fehler oder Netzwerkproblem

**Lösung**:
1. Browser-Console prüfen
2. Netzwerk-Tab im Dev-Tools prüfen
3. PHP-Fehlerm eldungen aktivieren

### Kein Zertifikat nach Completion

**Ursache**: Fortbildungs-Modul nicht aktiv

**Lösung**:
1. Fortbildungs-Modul aktivieren
2. Debug-Log prüfen
3. Duplikat-Check (verhindert doppelte Zertifikate)",
        'features' => [
            'Dynamische URL-Struktur',
            'Zeitbasiertes Progress-Tracking',
            'Skip-Forward-Prävention',
            'Automatische Zertifikate',
            'Batch-Import von Vimeo',
            'E-Mail-Benachrichtigungen',
            'Frontend-Manager',
            'Duplicate Detection'
        ],
        'keywords' => ['vimeo', 'webinar', 'video', 'tracking', 'zertifikat', 'cme', 'fortbildung', 'batch-import']
    ],
];

// Guides erstellen
foreach ($missing_guides as $module_id => $guide_data) {
    $guide_data['module_id'] = $module_id;
    $guide_data['last_updated'] = date('Y-m-d H:i:s');

    $json = json_encode($guide_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $file = $guides_dir . $module_id . '.json';

    file_put_contents($file, $json);
    echo "✓ Guide erstellt: {$module_id}\n";
}

echo "\n✅ Alle fehlenden Guides wurden erstellt!\n";
