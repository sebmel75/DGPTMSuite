# DGPTM EFN Manager – Installationsanleitung

## Übersicht

Der **EFN Manager** ist ein zentrales Modul für die Verwaltung der Einheitlichen Fortbildungsnummer (EFN) im DGPTM Plugin Suite System. Es konsolidiert alle EFN-bezogenen Funktionen aus verschiedenen Modulen in einem einzigen, wartungsfreundlichen Modul.

## Voraussetzungen

### Systemanforderungen

- **WordPress:** 5.8 oder höher
- **PHP:** 7.4 oder höher
- **DGPTM Plugin Suite:** Installiert und aktiviert
- **Erforderliche Bibliotheken:**
  - `dgptm-plugin-suite/libraries/fpdf/fpdf.php` (PDF-Generierung)
  - `dgptm-plugin-suite/libraries/class-code128.php` (Barcode-Generierung)

### Abhängigkeiten

**Erforderliche Module:**
- `crm-abruf` (Core Infrastructure) – Für Zoho CRM-Integration und `[zoho_api_data]` Shortcode

**Optionale externe Dienste:**
- **Zoho CRM** – Für EFN-Daten-Abruf
- **PrintNode** – Für Silent Server-seitiges Drucken (optional)
- **Zoho Functions** – Für Kiosk-Webhook-Validierung (optional)

## Installation

### Schritt 1: Modul-Aktivierung

1. Navigieren Sie im WordPress Admin-Bereich zu **DGPTM Suite → Dashboard**
2. Suchen Sie das Modul **"EFN Manager"** in der Kategorie "Utilities"
3. Überprüfen Sie, ob alle Abhängigkeiten erfüllt sind:
   - ✅ `crm-abruf` muss aktiviert sein
   - ✅ FPDF-Bibliothek muss vorhanden sein
   - ✅ Code128-Bibliothek muss vorhanden sein
4. Klicken Sie auf **"Aktivieren"**

### Schritt 2: Grundkonfiguration

Nach der Aktivierung navigieren Sie zu **Einstellungen → EFN Manager**.

#### 2.1 Allgemeine Einstellungen

**EFN Autofill beim Login:**
- **Aktiviert** (empfohlen): EFN wird automatisch beim ersten Login aus Zoho übernommen
- **Deaktiviert**: Benutzer müssen EFN manuell eingeben

#### 2.2 Kiosk-System (optional)

Wenn Sie das Self-Service-Kiosk-System verwenden möchten:

**Webhook-URL konfigurieren:**
```
https://www.zohoapis.eu/crm/v7/functions/{YOUR_FUNCTION_ID}/actions/execute?auth_type=apikey&zapikey={YOUR_API_KEY}
```

**Webhook-Format:**
Der Webhook erwartet POST JSON:
```json
{
  "arguments": {
    "code": "SCANNED_CODE_HERE"
  }
}
```

**Webhook-Antwort (erwartet):**
```json
{
  "details": {
    "output": {
      "statusefn": "found",
      "messageefn": "EFN gefunden",
      "name": "Max Mustermann",
      "efn": "123456789012345"
    }
  }
}
```

**Kiosk-Modus wählen:**
- **Browser**: Chrome Kiosk-Printing (benötigt `--kiosk-printing` Flag)
- **PrintNode**: Server-seitiges Silent Printing (siehe Schritt 2.4)

**Kiosk-Vorlage:**
Wählen Sie eine Standard-Etikettenvorlage (z.B. "LabelIdent EBL048X017PP")

#### 2.3 Druckkalibierung

Für präzisen Etikettendruck können Sie Korrekturwerte einstellen:

**Vertikale Kalibrierung:**
- **Oberste Reihe**: -5,0 mm (negativ = nach oben)
- **Unterste Reihe**: +5,0 mm (positiv = nach unten)

**Horizontale Kalibrierung:**
- **Linke Spalte**: -5,0 mm (negativ = nach links)
- **Rechte Spalte**: +5,0 mm (positiv = nach rechts)

**Hinweis:** Starten Sie mit den Standardwerten und passen Sie diese nach Testdrucken an.

#### 2.4 PrintNode-Konfiguration (optional)

Für serverseitiges Silent Printing:

1. Erstellen Sie einen Account bei [PrintNode](https://app.printnode.com/)
2. Generieren Sie einen API-Key im Dashboard (Account → API Keys)
3. Ermitteln Sie Ihre Printer-ID:
   - Via API: `GET https://api.printnode.com/printers` (mit Basic Auth)
   - Oder im PrintNode Dashboard unter "Printers"
4. Tragen Sie die Werte ein:
   - **API Key**: `PN-XXXXXXXX:YYYYYYYYYYYYYYYYYYYY`
   - **Printer ID**: z.B. `12345`

**Testdruck durchführen:**
Klicken Sie auf **"PrintNode-Testdruck senden"** – ein Test-PDF wird an den konfigurierten Drucker gesendet.

#### 2.5 Footer-Einstellungen

- **Footer anzeigen**: Ja/Nein
- **Abstand vom unteren Rand**: 7,0 mm (Standard)

### Schritt 3: Benutzerprofil-Konfiguration

Jeder WordPress-Benutzer kann seine EFN im Profil hinterlegen:

1. Navigieren Sie zu **Benutzer → Profil** (oder **Dein Profil**)
2. Scrollen Sie zum Abschnitt **"EFN (Einheitliche Fortbildungsnummer)"**
3. Optionen:
   - **Manuell eingeben**: 15-stellige Nummer eingeben
   - **Aus Zoho übernehmen**: Button "EFN aus Zoho übernehmen" klicken

Die EFN wird in der User-Meta als Feld `EFN` gespeichert.

### Schritt 4: Shortcodes einbinden

#### 4.1 Mobile Barcode (SVG, nur auf Mobilgeräten sichtbar)

```
[efn_barcode_mobile]
```

**Verwendung:**
- Zeigt EFN als Code128-Barcode an
- Nur auf Geräten < 768px Breite sichtbar
- Benötigt aktiven Benutzer mit EFN im Profil

#### 4.2 Label-Sheet Download

```
[efn_label_sheet default="LabelIdent EBL048X017PP (48,5×16,9, 4×16)"]
```

**Parameter:**
- `default`: Vorausgewählte Vorlage (optional)

**Funktionen:**
- Dropdown mit allen verfügbaren Vorlagen
- Benutzerdefinierte Maße möglich
- Name wird als Überschrift auf dem Bogen gedruckt
- PDF-Download als `EFN_Labels_{EFN}.pdf`

#### 4.3 Kiosk-System

```
[efn_kiosk webhook="https://..." mode="browser" debug="no" template="LabelIdent EBL048X017PP (48,5×16,9, 4×16)"]
```

**Parameter:**
- `webhook`: Webhook-URL (optional, nutzt Server-Default)
- `mode`: `browser` oder `printnode` (optional, nutzt Server-Default)
- `debug`: `yes` oder `no` (zeigt Debug-Informationen)
- `template`: Etikettenvorlage (optional, nutzt Server-Default)

**Verwendung:**
- Vollbild-Interface für Self-Service
- Barcode-Scanner-Integration
- Automatischer Druckstart nach Validierung

#### 4.4 JavaScript-basierter Barcode

```
[efn_barcode_js width="280" height="70"]
```

**Parameter:**
- `width`: Barcode-Breite in Pixel (Standard: 280)
- `height`: Barcode-Höhe in Pixel (Standard: 70)

**Hinweis:** Lädt JsBarcode-Bibliothek von CDN (jsDelivr)

## Kiosk-Setup für Chrome

Für den Browser-Kiosk-Modus benötigen Sie Chrome mit Kiosk-Printing:

### Windows

```cmd
"C:\Program Files\Google\Chrome\Application\chrome.exe" --kiosk --kiosk-printing "https://ihre-website.de/kiosk-seite/"
```

### Linux

```bash
google-chrome --kiosk --kiosk-printing "https://ihre-website.de/kiosk-seite/"
```

### macOS

```bash
/Applications/Google\ Chrome.app/Contents/MacOS/Google\ Chrome --kiosk --kiosk-printing "https://ihre-website.de/kiosk-seite/"
```

**Wichtig:**
- `--kiosk-printing` unterdrückt den Druckdialog
- Standarddrucker muss vorab im System konfiguriert sein

## Verfügbare Etikettenvorlagen

1. **Avery Zweckform 3667** (48.5×16.9 mm, 4×16 = 64 Etiketten)
2. **LabelIdent EBL048X017PP** (48,5×16,9 mm, 4×16 = 64 Etiketten)
3. **Zweckform L6011** (63.5×33.9 mm, 3×8 = 24 Etiketten)
4. **Zweckform L6021** (70×37 mm, 3×8 = 24 Etiketten)
5. **Avery L7160** (63.5×38.1 mm, 3×7 = 21 Etiketten)
6. **Avery L7563** (99.1×38.1 mm, 2×7 = 14 Etiketten)
7. **Zweckform L6021REV-25** (45.7×16.9 mm, 4×16 = 64 Etiketten)

**Benutzerdefinierte Vorlagen:**
Im Label-Sheet-Formular können Sie auch eigene Maße angeben (Dropdown → "Benutzerdefiniert").

## Fehlerbehebung

### EFN wird nicht gefunden

**Problem:** "Keine EFN gefunden" Meldung

**Lösungen:**
1. Überprüfen Sie, ob `crm-abruf` Modul aktiviert ist
2. Testen Sie `[zoho_api_data field="EFN"]` auf einer Seite
3. Prüfen Sie Zoho CRM-Verbindung in **DGPTM Suite → CRM Abruf**
4. Stellen Sie sicher, dass der Benutzer eingeloggt ist

### PDF wird nicht generiert

**Problem:** "FPDF nicht gefunden" oder "Code128 Klasse nicht gefunden"

**Lösungen:**
1. Überprüfen Sie, ob folgende Dateien existieren:
   - `dgptm-plugin-suite/libraries/fpdf/fpdf.php`
   - `dgptm-plugin-suite/libraries/class-code128.php`
2. Überprüfen Sie Dateiberechtigungen (lesbar)

### PrintNode-Druck funktioniert nicht

**Problem:** Testdruck schlägt fehl

**Lösungen:**
1. Überprüfen Sie API-Key Format: `PN-XXXXXXXX:YYYYYYYYYYYYYYYYYYYY`
2. Verifizieren Sie Printer-ID (muss numerisch sein)
3. Testen Sie API-Key mit cURL:
   ```bash
   curl -u "PN-XXXXXXXX:YYYYYYYYYYYYYYYYYYYY:" https://api.printnode.com/whoami
   ```
4. Prüfen Sie PrintNode-Client-Status (muss online sein)

### Kiosk-Webhook gibt Fehler zurück

**Problem:** "Webhook-Fehler" oder "EFN nicht gefunden"

**Lösungen:**
1. Testen Sie Webhook-URL direkt mit POST JSON:
   ```bash
   curl -X POST "https://ihre-webhook-url" \
     -H "Content-Type: application/json" \
     -d '{"arguments":{"code":"TEST123"}}'
   ```
2. Überprüfen Sie Webhook-Antwortformat (siehe Schritt 2.2)
3. Aktivieren Sie Debug-Modus: `debug="yes"` im Shortcode
4. Prüfen Sie Browser-Konsole auf JavaScript-Fehler

### Druckausrichtung stimmt nicht

**Problem:** Etiketten werden verschoben gedruckt

**Lösungen:**
1. Drucken Sie einen Testbogen
2. Messen Sie die Verschiebung in mm
3. Passen Sie Kalibrierungswerte an:
   - **Vertikal**: Top/Bottom Correction
   - **Horizontal**: Left/Right Correction
4. Wiederholen Sie den Prozess iterativ
5. Stellen Sie sicher, dass Drucker auf 100% Skalierung eingestellt ist (nicht "An Seite anpassen")

## Deinstallation

### Modul deaktivieren

1. Navigieren Sie zu **DGPTM Suite → Dashboard**
2. Suchen Sie "EFN Manager"
3. Klicken Sie auf **"Deaktivieren"**

**Hinweis:**
- Shortcodes werden entfernt
- Einstellungen bleiben in der Datenbank erhalten
- User-Meta-Feld `EFN` bleibt erhalten

### Vollständige Entfernung

Wenn Sie alle Daten löschen möchten:

```sql
-- WordPress-Optionen löschen
DELETE FROM wp_options WHERE option_name LIKE 'dgptm_kiosk_%';
DELETE FROM wp_options WHERE option_name LIKE 'dgptm_printnode_%';
DELETE FROM wp_options WHERE option_name LIKE 'dgptm_footer_%';
DELETE FROM wp_options WHERE option_name LIKE 'dgptm_debug_%';
DELETE FROM wp_options WHERE option_name = 'dgptm_default_template';
DELETE FROM wp_options WHERE option_name = 'dgptm_efn_autofill_on_init';

-- User-Meta EFN-Felder löschen (optional)
DELETE FROM wp_usermeta WHERE meta_key = 'EFN';
```

## Migration von alten Modulen

Falls Sie bereits EFN-Funktionen in anderen Modulen nutzen:

### Von `crm-abruf`

Der alte Shortcode `[efn_barcode]` aus `crm-abruf` wird zu `[efn_barcode_mobile]`.

**Suchen & Ersetzen:**
- Ersetzen Sie `[efn_barcode]` durch `[efn_barcode_mobile]` in allen Seiten/Posts

### Von `fortbildung/dgptm-efn-labels.php`

**Shortcodes bleiben kompatibel:**
- `[efn_label_sheet]` – unverändert
- `[efn_kiosk]` – unverändert
- `[efn_barcode]` → `[efn_barcode_js]` (JavaScript-Version)

**Einstellungen:**
Die Einstellungsseite wechselt von "Einstellungen → EFN-Print" zu "Einstellungen → EFN Manager".

Alle bestehenden Optionen werden automatisch übernommen (gleiche Option-Namen).

### Von `fortbildung-liste-plugin.php`

**Benutzerprofil-Feld:**
- Bleibt unverändert (User-Meta `EFN`)
- Autofill-Funktion bleibt identisch

**Einstellungen:**
- Autofill-Option wird zu "dgptm_efn_autofill_on_init"

## Support & Kontakt

Bei Fragen oder Problemen:

- **E-Mail:** geschaeftsstelle@dgptm.de
- **Issue Tracker:** GitHub (falls vorhanden)
- **Dokumentation:** `README.md` im Modul-Verzeichnis

## Changelog

### Version 1.0.0 (2025-01-20)

**Initial Release:**
- Konsolidierung aller EFN-Funktionen aus 3 Modulen
- 4 Shortcodes: `[efn_barcode_mobile]`, `[efn_label_sheet]`, `[efn_kiosk]`, `[efn_barcode_js]`
- 7 vordefinierte Etikettenvorlagen
- PrintNode-Integration für Silent Printing
- Zoho CRM-Integration via `crm-abruf` Modul
- Benutzerprofil-Verwaltung mit Autofill
- Kiosk-System mit Webhook-Validierung
- Präzise Druckkalibierung (vertikal & horizontal)
- Footer-Konfiguration
- Admin-Einstellungsseite mit Testdruck-Funktion
