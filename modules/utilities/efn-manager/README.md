# DGPTM EFN Manager

**Version:** 1.0.0
**Author:** Sebastian Melzer
**Kategorie:** Utilities
**Abhängigkeiten:** crm-abruf

## Beschreibung

Der **EFN Manager** ist ein zentrales Verwaltungssystem für die **Einheitliche Fortbildungsnummer (EFN)** im DGPTM Plugin Suite. Das Modul konsolidiert alle EFN-bezogenen Funktionen aus drei verschiedenen Modulen in ein einheitliches, wartungsfreundliches System.

### Hauptfunktionen

1. **Mobile Barcodes** – Code128-B SVG-Rendering für Smartphone-Displays
2. **A4-Aufkleberbogen** – PDF-Generierung mit 7 vordefinierten Vorlagen + benutzerdefinierte Maße
3. **Self-Service-Kiosk** – Scanner-Integration mit automatischem Druckstart
4. **PrintNode Silent Printing** – Server-seitiges Drucken ohne Browser-Dialog
5. **Benutzerprofil-Verwaltung** – EFN-Feld mit Zoho-Autofill
6. **Zoho CRM-Integration** – Automatischer EFN-Abruf aus Zoho
7. **Webhook-Verarbeitung** – Validierung über Zoho Functions
8. **Präzise Druckkalibierung** – 2-Punkt-Vertikal- und Rand-Horizontal-Korrektur

## Architektur

### Klassenstruktur

```
dgptm-efn-manager.php
├── DGPTM_EFN_Barcode_Offline_Mobile (Singleton)
│   ├── Code128-B Pattern-Array
│   ├── shortcode_render() → [efn_barcode_mobile]
│   └── inject_mobile_css() → Responsive Styles
│
├── DGPTM_EFN_Labels (Singleton)
│   ├── $templates (7 vordefinierte A4-Formate)
│   ├── render_labels_pdf() → FPDF + Code128
│   ├── shortcode_label_sheet() → [efn_label_sheet]
│   ├── shortcode_kiosk() → [efn_kiosk]
│   ├── shortcode_barcode() → [efn_barcode_js]
│   ├── ajax_kiosk_print() → AJAX-Handler
│   └── strict_post_no_auth() → Webhook ohne Auth-Header
│
├── dgptm_efn_user_profile_field() → Profilfeld-Rendering
├── dgptm_efn_user_profile_save() → User-Meta Speicherung
├── dgptm_efn_fetch_from_zoho() → AJAX-Abruf
├── Autofill init Hook → Einmaliges Befüllen
└── Admin Settings Page → Einstellungen UI
```

### Datenfluss

#### Label-Sheet Download

```
Benutzer → Shortcode [efn_label_sheet]
         → Formular (Template-Auswahl)
         → GET admin-post.php?action=dgptm_efn_labels
         → handle_download()
         → get_efn_from_shortcode() → [zoho_api_data field="EFN"]
         → render_labels_pdf()
         → FPDF + DGPTM_Code128::draw()
         → PDF-Download (EFN_Labels_{EFN}.pdf)
```

#### Kiosk-System

```
Benutzer → Shortcode [efn_kiosk]
         → Scanner scannt Code
         → JavaScript fetch → admin-ajax.php?action=dgptm_kiosk_print
         → ajax_kiosk_print()
         → strict_post_no_auth(webhook, {code})
         → Zoho Functions → Validierung
         → Response: {statusefn, messageefn, name, efn}
         → render_labels_pdf(efn, template, name, args)
         → Modus-Check:
             ├── Browser → save_pdf_tmp() → URL → iframe.print()
             └── PrintNode → POST /printjobs → Silent Print
```

#### EFN-Autofill

```
User Login → init Hook
          → get_user_meta(EFN) === ''
          → [zoho_api_data field="EFN"]
          → preg_replace('/\D+/', '')
          → update_user_meta(EFN, $digits)
```

## Shortcode-Referenz

### 1. Mobile Barcode (SVG)

**Shortcode:**
```
[efn_barcode_mobile]
```

**Beschreibung:**
- Generiert Code128-B Barcode als inline SVG
- Nur auf Mobilgeräten sichtbar (`@media (min-width:768px) { display:none }`)
- Ruft EFN über `[zoho_api_data field="EFN"]` ab
- Extrahiert nur Ziffern (15-stellig)

**Ausgabe:**
```html
<div class="dgptm-efn-mobile-only">
  <svg class="dgptm-efn-barcode" viewBox="0 0 {width} 40">
    <rect x="{x}" y="0" width="{w}" height="40" />
    ...
  </svg>
  <div class="dgptm-efn-label">EFN: 123456789012345</div>
</div>
```

**Code128-Implementierung:**
- Start B (Pattern 104)
- Data Characters (ASCII - 32)
- Checksum (sum % 103)
- Stop (Pattern 106)

---

### 2. Label-Sheet Download

**Shortcode:**
```
[efn_label_sheet default="LabelIdent EBL048X017PP (48,5×16,9, 4×16)"]
```

**Parameter:**
| Parameter | Typ    | Standard                                    | Beschreibung                    |
|-----------|--------|---------------------------------------------|---------------------------------|
| `default` | string | `LabelIdent EBL048X017PP (48,5×16,9, 4×16)` | Vorausgewählte Etikettenvorlage |

**Verfügbare Vorlagen:**

| Name                                    | Format (mm)  | Layout | Etiketten | Hersteller       |
|-----------------------------------------|--------------|--------|-----------|------------------|
| Avery Zweckform 3667                    | 48.5 × 16.9  | 4×16   | 64        | Avery Zweckform  |
| LabelIdent EBL048X017PP                 | 48.5 × 16.9  | 4×16   | 64        | LabelIdent       |
| Zweckform L6011                         | 63.5 × 33.9  | 3×8    | 24        | Avery Zweckform  |
| Zweckform L6021                         | 70 × 37      | 3×8    | 24        | Avery Zweckform  |
| Avery L7160                             | 63.5 × 38.1  | 3×7    | 21        | Avery            |
| Avery L7563                             | 99.1 × 38.1  | 2×7    | 14        | Avery            |
| Zweckform L6021REV-25                   | 45.7 × 16.9  | 4×16   | 64        | Avery Zweckform  |

**Benutzerdefinierte Vorlagen:**
- Dropdown-Option "Benutzerdefiniert (A4)"
- Eingabefelder für alle Parameter:
  - Seite: Breite × Höhe (210 × 297 mm)
  - Spalten × Zeilen
  - Etikett: Breite × Höhe
  - Rand: Links / Oben
  - Abstand: Horizontal / Vertikal

**Ausgabe:**
- Formular mit Template-Auswahl
- Namenseingabe für Überschrift
- Button "PDF jetzt herunterladen"
- Download: `EFN_Labels_{EFN}.pdf`

**PDF-Layout:**
```
┌───────────────────────────────────────────┐
│ DGPTM-EFN-Bogen von: Max Mustermann       │ ← Header (Zeile 1)
├───────────┬───────────┬───────────┬───────┤
│ ┌───────┐ │ ┌───────┐ │ ┌───────┐ │ ...   │
│ │Barcode│ │ │Barcode│ │ │Barcode│ │       │
│ └───────┘ │ └───────┘ │ └───────┘ │       │
│ EFN: ...  │ EFN: ...  │ EFN: ...  │       │
├───────────┼───────────┼───────────┼───────┤
│    ...    │    ...    │    ...    │       │
└───────────┴───────────┴───────────┴───────┘
│        Erstellt von DGPTM                  │ ← Footer (optional)
└───────────────────────────────────────────┘
```

---

### 3. Kiosk-System

**Shortcode:**
```
[efn_kiosk webhook="https://..." mode="browser" debug="no" template="..."]
```

**Parameter:**
| Parameter  | Typ    | Standard        | Beschreibung                                        |
|------------|--------|-----------------|-----------------------------------------------------|
| `webhook`  | string | (Server-Option) | Zoho Functions Webhook-URL                          |
| `mode`     | string | `browser`       | `browser` oder `printnode`                          |
| `debug`    | string | `no`            | `yes` zeigt Debug-Informationen                     |
| `template` | string | (Server-Option) | Etikettenvorlage                                    |

**Webhook-Kommunikation:**

**Request:**
```json
POST {webhook-url}
Content-Type: application/json

{
  "arguments": {
    "code": "SCANNED_CODE"
  }
}
```

**Response (erfolgreich):**
```json
{
  "details": {
    "output": {
      "statusefn": "found",
      "messageefn": "EFN erfolgreich validiert",
      "name": "Max Mustermann",
      "efn": "123456789012345"
    }
  }
}
```

**Response (nicht gefunden):**
```json
{
  "details": {
    "output": {
      "statusefn": "notfound",
      "messageefn": "Code ungültig oder abgelaufen"
    }
  }
}
```

**Alternative Payload-Strukturen:**
1. `details.output` (Objekt)
2. `details.output` (JSON-String → geparst)
3. `details.userMessage[2]` (Fallback)

**Ausgabe:**
```html
<div class="dgptm-kiosk" style="min-height:100vh; background:#111; ...">
  <h1>EFN Bogen selber drucken</h1>
  <form>
    <input id="dgptm-kiosk-code" placeholder="Code scannen …" />
    <button id="dgptm-kiosk-send">Senden</button>
  </form>
  <div id="dgptm-kiosk-status"></div>
  <div id="dgptm-kiosk-debug"></div>
  <iframe id="dgptm-kiosk-printframe"></iframe>
</div>
```

**JavaScript-Flow:**
1. User scannt Code oder tippt ein
2. `fetch(admin-ajax.php, {action: 'dgptm_kiosk_print', code, webhook, ...})`
3. Server: Webhook POST → Validierung
4. Server: PDF-Generierung mit Kalibierung
5. Response: `{ok: true, mode: 'browser', url: '...'}`
6. Client: `iframe.src = url` → `iframe.contentWindow.print()`

**PrintNode-Modus:**
1. Server: PDF → Base64
2. Server: POST `https://api.printnode.com/printjobs`
3. Response: `{ok: true, mode: 'printnode', message: '...'}`
4. Client: Statusmeldung (kein Browser-Dialog)

---

### 4. JavaScript-Barcode

**Shortcode:**
```
[efn_barcode_js width="280" height="70"]
```

**Parameter:**
| Parameter | Typ | Standard | Beschreibung            |
|-----------|-----|----------|-------------------------|
| `width`   | int | `280`    | Barcode-Breite (px)     |
| `height`  | int | `70`     | Barcode-Höhe (px)       |

**Externe Abhängigkeit:**
```javascript
https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js
```

**Ausgabe:**
```html
<div class="dgptm-efn-barcode" data-efn="123456789012345">
  <div class="dgptm-efn-barcode-inner">
    <svg id="dgptm-efn-barcode-svg-{unique}"></svg>
    <div class="dgptm-efn-barcode-text">EFN: 123456789012345</div>
  </div>
</div>
<script>
  window.JsBarcode(svg, efn, {
    format: "CODE128",
    width: 2,
    height: 70,
    displayValue: false,
    margin: 0
  });
</script>
```

**Hinweis:** Sichtbar auf allen Geräten (keine Responsive-Beschränkung wie `[efn_barcode_mobile]`)

## Benutzerprofil-Integration

### User-Meta-Feld

**Feld-Name:** `EFN`
**Typ:** String (15 Ziffern)
**Speicherort:** `wp_usermeta` Tabelle

### Profil-UI

Fügt im WordPress-Benutzerprofil einen Abschnitt hinzu:

```html
<h2>EFN (Einheitliche Fortbildungsnummer)</h2>
<table class="form-table">
  <tr>
    <th><label for="dgptm_efn">EFN</label></th>
    <td>
      <input type="text" name="dgptm_efn" value="..." />
      <button id="dgptm-efn-fetch-btn">EFN aus Zoho übernehmen</button>
      <span id="dgptm-efn-fetch-msg"></span>
    </td>
  </tr>
</table>
```

### AJAX-Abruf

**Endpoint:** `admin-ajax.php?action=dgptm_efn_fetch_from_zoho`

**Request:**
```javascript
jQuery.post(ajaxurl, {
  action: 'dgptm_efn_fetch_from_zoho'
}, function(response) {
  if (response.success) {
    $('#dgptm_efn').val(response.data.efn);
  }
});
```

**Response:**
```json
{
  "success": true,
  "data": {
    "efn": "123456789012345"
  }
}
```

### Autofill beim Login

**Hook:** `init` (Priority: default)

**Logik:**
```php
if (is_user_logged_in() && get_option('dgptm_efn_autofill_on_init') === '1') {
  $current_efn = get_user_meta(get_current_user_id(), 'EFN', true);
  if ($current_efn === '') {
    $efn = do_shortcode('[zoho_api_data field="EFN"]');
    $efn = preg_replace('/\D+/', '', $efn);
    if ($efn) update_user_meta(get_current_user_id(), 'EFN', $efn);
  }
}
```

**Voraussetzung:**
- Benutzer muss eingeloggt sein
- EFN-Feld muss leer sein
- Zoho CRM muss verbunden sein (`crm-abruf` aktiv)

## Admin-Einstellungen

**Pfad:** WordPress Admin → Einstellungen → EFN Manager

### Einstellungsgruppen

#### 1. Allgemeine Einstellungen

**Optionen:**
- `dgptm_efn_autofill_on_init` (string: '1' oder '0')
  - **Standard:** `'1'` (aktiviert)
  - **Beschreibung:** EFN beim Login automatisch aus Zoho übernehmen

#### 2. Kiosk-System

**Optionen:**
- `dgptm_kiosk_webhook` (string, URL)
  - **Format:** `https://www.zohoapis.eu/crm/v7/functions/{id}/actions/execute?auth_type=apikey&zapikey={key}`

- `dgptm_kiosk_mode` (string: 'browser' oder 'printnode')
  - **Standard:** `'browser'`
  - **Hinweis:** Server erzwingt 'printnode', wenn API-Key & Printer-ID gesetzt

- `dgptm_kiosk_template` (string)
  - **Standard:** `'LabelIdent EBL048X017PP (48,5×16,9, 4×16)'`

- `dgptm_default_template` (string)
  - **Standard:** `'LabelIdent EBL048X017PP (48,5×16,9, 4×16)'`

- `dgptm_debug_default` (string: 'yes' oder 'no')
  - **Standard:** `'no'`

#### 3. Druckkalibierung

**Vertikale 2-Punkt-Kalibrierung:**
- `dgptm_kiosk_top_correction_mm` (float)
  - **Standard:** `-5.0`
  - **Bereich:** -20.0 bis +20.0
  - **Beschreibung:** Negativ = nach oben, positiv = nach unten

- `dgptm_kiosk_bottom_correction_mm` (float)
  - **Standard:** `5.0`
  - **Bereich:** -20.0 bis +20.0
  - **Beschreibung:** Positiv = nach unten, negativ = nach oben

**Horizontale Rand-Kalibrierung:**
- `dgptm_kiosk_left_correction_mm` (float)
  - **Standard:** `-5.0`
  - **Bereich:** -20.0 bis +20.0
  - **Beschreibung:** Negativ = nach links, positiv = nach rechts

- `dgptm_kiosk_right_correction_mm` (float)
  - **Standard:** `5.0`
  - **Bereich:** -20.0 bis +20.0
  - **Beschreibung:** Negativ = nach links, positiv = nach rechts

**Kalibrierungs-Algorithmus:**
```php
// Vertikal (lineare Interpolation oben → unten)
$y_offset = $top_correction_mm;
$y_drift  = $bottom_correction_mm - $top_correction_mm;
$y = $margin_top + $y_offset + ($row_index / ($total_rows - 1)) * $y_drift;

// Horizontal (lineare Interpolation links → rechts)
$h_drift = ($col_index / ($total_cols - 1)) * ($right_correction - $left_correction);
$x = $margin_left + $h_drift;
```

#### 4. Footer-Einstellungen

**Optionen:**
- `dgptm_footer_show` (string: 'yes' oder 'no')
  - **Standard:** `'yes'`

- `dgptm_footer_from_bottom_mm` (float)
  - **Standard:** `7.0`
  - **Beschreibung:** Abstand Unterkante Footer → Papierrand

**Footer-Text:** "Erstellt von DGPTM"

#### 5. PrintNode Silent Printing

**Optionen:**
- `dgptm_printnode_api_key` (string)
  - **Format:** `PN-XXXXXXXX:YYYYYYYYYYYYYYYYYYYY`

- `dgptm_printnode_printer_id` (integer)
  - **Beispiel:** `12345`

**PrintNode API-Endpoint:**
```
POST https://api.printnode.com/printjobs
Authorization: Basic {base64(api_key + ':')}
Content-Type: application/json

{
  "printerId": 12345,
  "title": "EFN Labels 123456789012345",
  "contentType": "pdf_base64",
  "content": "{base64_encoded_pdf}",
  "source": "DGPTM-Labels-Kiosk"
}
```

**Testdruck:**
- Button "PrintNode-Testdruck senden"
- Action: `admin-post.php?action=dgptm_printnode_test`
- Generiert Test-PDF mit Zeitstempel
- Sendet an konfigurierte Printer-ID

## Sicherheit

### Nonce-Validierung

**Label-Sheet Download:**
```php
wp_verify_nonce($_REQUEST['dgptm_efn_nonce'], 'dgptm_efn_pdf')
```

**Kiosk AJAX:**
```php
check_ajax_referer('dgptm_kiosk', 'nonce')
```

**Testdruck:**
```php
check_admin_referer('dgptm_printnode_test')
```

### Capability-Checks

**Admin-Einstellungen:**
```php
if (!current_user_can('manage_options')) return;
```

**Benutzerprofil:**
```php
if (!current_user_can('edit_user', $user_id)) return;
```

### Sanitization

**EFN (nur Ziffern):**
```php
preg_replace('/\D+/', '', $input)
```

**URLs:**
```php
esc_url_raw($input)
```

**Text:**
```php
sanitize_text_field($input)
```

**Output Escaping:**
- `esc_html()` – HTML-Kontext
- `esc_attr()` – HTML-Attribute
- `esc_url()` – URL-Ausgabe

### CSRF-Schutz

Alle Formulare und AJAX-Requests verwenden WordPress Nonces:
- Zeitbasierte Tokens (12h/24h Gültigkeit)
- User-spezifisch
- Action-spezifisch

## Performance

### Caching-Strategie

**Keine Caching-Layer:**
- EFN-Daten sind user-spezifisch
- Zoho-Abfragen in Echtzeit (via `crm-abruf`)
- PDF-Generierung on-the-fly

**Temporäre Dateien:**
- Kiosk-PDFs: `wp-content/uploads/efn_kiosk_{timestamp}.pdf`
- Werden nach Download/Druck nicht gelöscht (Browser-Cache)
- Empfehlung: Cron-Job für Cleanup (älter als 24h)

### Optimierungen

**FPDF:**
- Keine externe Bibliothek (lokale FPDF-Klasse)
- Keine Bildverarbeitung (nur Barcodes via Code128)

**JavaScript:**
- JsBarcode: CDN-geladen (jsDelivr, schnell)
- Lazy Loading: Nur wenn Shortcode auf Seite

**AJAX:**
- Keine Polling (event-basiert)
- Single Request pro Scan

## Erweiterungen

### Eigene Etikettenvorlagen hinzufügen

**Code-Location:** `dgptm-efn-manager.php:159-182`

```php
private $templates = array(
    'Ihre Vorlage (70×42, 3×7)' => array(
        'page_w'  => 210,    // A4 Breite
        'page_h'  => 297,    // A4 Höhe
        'cols'    => 3,      // Spalten
        'rows'    => 7,      // Zeilen
        'label_w' => 70.0,   // Etikett-Breite (mm)
        'label_h' => 42.0,   // Etikett-Höhe (mm)
        'margin_l'=> 5.0,    // Rand links (mm)
        'margin_t'=> 10.0,   // Rand oben (mm)
        'h_space' => 2.5,    // Horiz. Abstand (mm)
        'v_space' => 0.0,    // Vert. Abstand (mm)
    ),
);
```

**Berechnung prüfen:**
```
Gesamt-Breite = margin_l + (cols * label_w) + ((cols-1) * h_space) + margin_r
Gesamt-Höhe   = margin_t + (rows * label_h) + ((rows-1) * v_space) + margin_b
```

### Webhook-Payload anpassen

**Code-Location:** `dgptm-efn-manager.php:613-661`

**Aktuell unterstützt:**
- `details.output.statusefn` / `details.output.messageefn` (Priorität 1)
- `details.userMessage[2]` (Fallback)
- `status` / `message` (Root-Level Fallback)

**Neue Struktur hinzufügen:**
```php
// Nach Zeile 646 einfügen
if (!$payload && isset($json['data']['efn_info'])) {
    $payload = $json['data']['efn_info'];
}
```

### Zusätzliche Barcode-Formate

**Code-Location:** `dgptm-efn-manager.php:113-149`

Aktuell: Code128-B (ASCII-optimiert)

**Code128-C hinzufügen (numerisch-optimiert):**
```php
// Ändern Sie Start-Code auf 105 (Start C)
$values = [105]; // Start C statt 104

// Paare von Ziffern → Wert 0-99
for($i=0; $i<$len; $i+=2){
    if ($i+1 < $len) {
        $pair = substr($digits, $i, 2);
        $values[] = intval($pair);
    }
}
```

## Testing

### Unit-Tests (Empfehlung)

**Test-Cases:**
1. EFN-Validierung (15 Ziffern)
2. Code128-Checksum-Berechnung
3. PDF-Template-Validierung
4. Webhook-Response-Parsing
5. Kalibrierungs-Mathematik

**Beispiel (PHPUnit):**
```php
public function testEFNValidation() {
    $valid_efn = '123456789012345';
    $result = preg_replace('/\D+/', '', $valid_efn);
    $this->assertEquals(15, strlen($result));

    $invalid_efn = 'ABC123';
    $result = preg_replace('/\D+/', '', $invalid_efn);
    $this->assertEquals(3, strlen($result)); // Nur 3 Ziffern
}
```

### Manuelles Testing

**Checkliste:**
- [ ] Modul aktivieren → keine PHP-Fehler
- [ ] EFN im Profil speichern → User-Meta korrekt
- [ ] "Aus Zoho übernehmen" → AJAX funktioniert
- [ ] `[efn_barcode_mobile]` → SVG rendert auf Mobile
- [ ] `[efn_label_sheet]` → PDF-Download funktioniert
- [ ] `[efn_kiosk]` → Scanner → PDF → Druck
- [ ] PrintNode-Testdruck → Drucker gibt aus
- [ ] Kalibr

ierung → Etiketten exakt ausgerichtet
- [ ] Footer → Position korrekt

## Migration & Kompatibilität

### Alte Shortcodes

**Aus `crm-abruf` (veraltet):**
- `[efn_barcode]` → **NEU:** `[efn_barcode_mobile]`

**Aus `fortbildung/dgptm-efn-labels.php` (kompatibel):**
- `[efn_label_sheet]` ✅ identisch
- `[efn_kiosk]` ✅ identisch
- `[efn_barcode]` → **NEU:** `[efn_barcode_js]`

### Datenbank-Optionen

**Wiederverwendet (kompatibel):**
- `dgptm_kiosk_webhook`
- `dgptm_kiosk_mode`
- `dgptm_kiosk_template`
- `dgptm_default_template`
- `dgptm_debug_default`
- `dgptm_kiosk_top_correction_mm`
- `dgptm_kiosk_bottom_correction_mm`
- `dgptm_kiosk_left_correction_mm`
- `dgptm_kiosk_right_correction_mm`
- `dgptm_footer_from_bottom_mm`
- `dgptm_footer_show`
- `dgptm_printnode_api_key`
- `dgptm_printnode_printer_id`

**Neu hinzugefügt:**
- `dgptm_efn_autofill_on_init`

### User-Meta

**Kompatibel:**
- Feld-Name `EFN` bleibt identisch
- Keine Datenmigration nötig

## Bekannte Einschränkungen

1. **FPDF-Schriftarten:** Nur Standard-Fonts (Helvetica, Arial, Times)
2. **Barcode-Format:** Nur Code128-B (kein QR-Code, kein EAN)
3. **Etiketten:** Nur A4-Format (kein Letter/Legal)
4. **PrintNode:** Keine Duplex/Farb-Optionen konfigurierbar
5. **Zoho-Feld:** Hardcoded auf `field="EFN"` (nicht konfigurierbar)
6. **Browser-Kiosk:** Erfordert Chrome mit `--kiosk-printing` Flag

## Roadmap (geplante Features)

- [ ] QR-Code-Unterstützung (zusätzlich zu Code128)
- [ ] Mehrsprachigkeit (i18n/l10n)
- [ ] Export als eigenständiges Plugin
- [ ] REST-API-Endpoint für externe Systeme
- [ ] Statistik-Dashboard (gedruckte Etiketten/Tag)
- [ ] E-Mail-Versand von PDFs
- [ ] Batch-Generierung (mehrere EFNs auf einem Bogen)

## Support & Beiträge

**Bug-Reports:**
- GitHub Issues (falls vorhanden)
- E-Mail: geschaeftsstelle@dgptm.de

**Feature-Requests:**
- Via GitHub Discussions oder E-Mail

**Code-Beiträge:**
- Fork → Branch → Pull Request
- Coding Standards: WordPress Coding Standards
- PSR-12 für moderne PHP-Teile

## Lizenz

**GPL v2 oder höher**

Dieses Modul ist Teil der DGPTM Plugin Suite und unterliegt der GNU General Public License v2.0.

---

**Autor:** Sebastian Melzer
**Datum:** 2025-01-20
**Version:** 1.0.0
