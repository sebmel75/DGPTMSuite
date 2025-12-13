# Debugging Guide - Vimeo Webinare

## Problem: Webinar wird nicht angezeigt

### Schritt 1: Überprüfen Sie die Fehlermeldung

Wenn Sie eine der folgenden Fehlermeldungen sehen, folgen Sie den entsprechenden Lösungen:

#### Fehler: "Keine Webinar-ID angegeben"
**Ursache:** Der Shortcode hat kein `id` Attribut

**Lösung:**
```
Falsch: [vimeo_webinar]
Richtig: [vimeo_webinar id="123"]
```

Die ID ist die Post-ID des Webinars. Finden Sie diese hier:
1. WordPress Admin → **Webinare**
2. Bewegen Sie die Maus über ein Webinar
3. In der URL sehen Sie: `post=123` ← Dies ist die ID

#### Fehler: "Webinar mit ID X nicht gefunden"
**Ursache:** Die angegebene Post-ID existiert nicht

**Lösung:**
1. Überprüfen Sie, ob das Webinar existiert: **Webinare → Alle Webinare**
2. Verwenden Sie die korrekte Post-ID
3. Stellen Sie sicher, dass das Webinar veröffentlicht ist (Status: "Veröffentlicht")

#### Fehler: "Post ID X ist kein Webinar"
**Ursache:** Die ID gehört zu einem anderen Post-Typ (z.B. Seite, Beitrag)

**Lösung:**
Verwenden Sie die ID eines Webinars, nicht einer Seite oder eines Beitrags.

#### Fehler: "Vimeo Video ID fehlt"
**Ursache:** Das Webinar hat keine Vimeo ID in den ACF-Feldern

**Lösung:**
1. WordPress Admin → **Webinare → Webinar bearbeiten**
2. Scrollen Sie zu **Webinar Einstellungen**
3. Füllen Sie das Feld **Vimeo Video ID** aus
4. Nur die Zahlen! z.B. `123456789` (nicht die vollständige URL)
5. Klicken Sie auf **Aktualisieren**

### Schritt 2: Debug-Modus aktivieren

Fügen Sie diese Zeile temporär in Ihre `wp-config.php` ein:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Dann öffnen Sie die Webinar-Seite und prüfen Sie:
`wp-content/debug.log`

### Schritt 3: Überprüfen Sie ACF-Felder

**Test 1: Sind ACF-Felder registriert?**
1. WordPress Admin → **Webinare → Webinar bearbeiten**
2. Sie sollten sehen: **Webinar Einstellungen** Box
3. Darin: Vimeo Video ID, Erforderlicher Fortschritt, etc.

Falls NICHT sichtbar:
- ACF Plugin aktiviert? **Plugins → Advanced Custom Fields**
- Modul aktiviert? **DGPTM Suite → Dashboard → Vimeo Webinare**

**Test 2: Können Sie Werte speichern?**
1. Bearbeiten Sie ein Webinar
2. Geben Sie eine Test-Vimeo-ID ein: `123456789`
3. Speichern Sie
4. Laden Sie die Seite neu
5. Ist der Wert noch da?

Falls NICHT:
- Prüfen Sie Datenbankberechtigungen
- Leeren Sie WordPress Cache (falls Object Cache aktiv)

### Schritt 4: Testen Sie die Shortcode-Ausgabe

Fügen Sie temporär Debug-Code zum Player-Template hinzu:

**Bearbeiten Sie:** `templates/player.php`

**Fügen Sie GANZ OBEN ein (nach Zeile 7):**
```php
<?php
// DEBUG
echo '<div style="background: #f0f0f0; padding: 15px; margin: 20px 0; border: 2px solid #000;">';
echo '<h3>DEBUG INFO</h3>';
echo '<p><strong>Post ID:</strong> ' . $post_id . '</p>';
echo '<p><strong>Vimeo ID:</strong> ' . $vimeo_id . '</p>';
echo '<p><strong>Completion %:</strong> ' . $completion_percentage . '</p>';
echo '<p><strong>Progress:</strong> ' . $progress . '%</p>';
echo '<p><strong>Completed:</strong> ' . ($is_completed ? 'Ja' : 'Nein') . '</p>';
echo '<p><strong>User ID:</strong> ' . $user_id . '</p>';

// Test ACF
$test_vimeo = get_field('vimeo_id', $post_id);
echo '<p><strong>ACF Test (Vimeo ID):</strong> ' . ($test_vimeo ?: 'LEER!') . '</p>';

echo '</div>';
?>
```

Laden Sie die Seite neu. Sie sollten jetzt alle Variablen sehen.

**Erwartete Ausgabe:**
```
DEBUG INFO
Post ID: 123
Vimeo ID: 987654321
Completion %: 90
Progress: 0%
Completed: Nein
User ID: 1
ACF Test (Vimeo ID): 987654321
```

**Falls "Vimeo ID" oder "ACF Test" leer sind:**
→ ACF-Felder sind nicht korrekt gespeichert oder werden nicht geladen

**WICHTIG:** Entfernen Sie den Debug-Code nach dem Test!

### Schritt 5: Browser-Konsole prüfen

1. Öffnen Sie die Webinar-Seite
2. Drücken Sie `F12` (Developer Tools)
3. Gehen Sie zum Tab **Console**
4. Suchen Sie nach Fehlermeldungen (rot)

**Häufige Fehler:**

**Fehler:** `Vimeo is not defined`
**Lösung:** Vimeo Player API wird nicht geladen
- Prüfen Sie: Tab **Network** → Suchen Sie nach `player.js`
- Falls 404: Internetverbindung prüfen oder Firewall/Ad-Blocker

**Fehler:** `vwData is not defined`
**Lösung:** JavaScript nicht korrekt initialisiert
- Prüfen Sie: Tab **Sources** → Suchen Sie nach `script.js`
- Falls nicht vorhanden: Plugin-URL falsch oder Cache-Problem

**Fehler:** `$ is not a function` oder `jQuery is not defined`
**Lösung:** jQuery nicht geladen
- WordPress sollte jQuery automatisch laden
- Prüfen Sie Theme-Konfiguration

### Schritt 6: Asset-Loading prüfen

**Sind CSS/JS geladen?**

1. Öffnen Sie die Webinar-Seite
2. Rechtsklick → "Seitenquelltext anzeigen"
3. Suchen Sie (Strg+F):
   - `vw-style` → CSS sollte gefunden werden
   - `vw-script` → JavaScript sollte gefunden werden
   - `vimeo-player` → Vimeo API sollte gefunden werden

**Falls NICHT gefunden:**
- Assets werden nicht eingebunden
- Prüfen Sie `force_enqueue_assets()` Funktion
- Prüfen Sie, ob Shortcode erkannt wird

### Schritt 7: Template-Pfad prüfen

**Test:** Kann das Template geladen werden?

Fügen Sie in `dgptm-vimeo-webinare.php` nach Zeile 298 ein:
```php
// DEBUG
$template_path = $this->plugin_path . 'templates/player.php';
if (!file_exists($template_path)) {
    return '<p>FEHLER: Template nicht gefunden: ' . $template_path . '</p>';
}
```

Falls diese Meldung erscheint:
→ Plugin-Pfad ist falsch konfiguriert

### Schritt 8: Vimeo iframe-Embed testen

**Test:** Funktioniert Vimeo generell?

Erstellen Sie eine Test-Seite mit diesem Code:
```html
<iframe
    src="https://player.vimeo.com/video/123456789"
    width="640"
    height="360"
    frameborder="0"
    allow="autoplay; fullscreen; picture-in-picture"
    allowfullscreen>
</iframe>
```

Ersetzen Sie `123456789` mit Ihrer echten Vimeo ID.

**Falls Video NICHT lädt:**
- Vimeo ID ist falsch
- Video ist auf "Privat" gesetzt in Vimeo
- Embedding ist deaktiviert in Vimeo-Einstellungen

**Vimeo-Einstellungen prüfen:**
1. Gehen Sie zu vimeo.com
2. Wählen Sie Ihr Video
3. Klicken Sie auf **Settings**
4. Tab **Privacy**
5. **"Who can embed this video?"** → Muss auf **"Anyone"** stehen

## Häufige Probleme & Lösungen

### Problem: "Seite ist leer / nur Header sichtbar"

**Ursache:** PHP-Fehler verhindert Ausgabe

**Lösung:**
1. Aktivieren Sie WP_DEBUG (siehe Schritt 2)
2. Prüfen Sie `wp-content/debug.log`
3. Suchen Sie nach "Fatal error" oder "Parse error"
4. Fehler beheben oder Support kontaktieren

### Problem: "CSS ist nicht geladen / sieht hässlich aus"

**Ursache:** CSS-Datei wird nicht eingebunden oder Pfad ist falsch

**Lösung:**
1. Prüfen Sie Browser DevTools → Tab **Network**
2. Suchen Sie nach `style.css`
3. Falls **404 Fehler**: Pfad ist falsch
4. Prüfen Sie `plugin_url` Variable in `force_enqueue_assets()`

**Temporärer Fix:**
Fügen Sie in `wp-content/themes/[ihr-theme]/functions.php` ein:
```php
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('vw-style',
        plugin_dir_url(__FILE__) . '../plugins/dgptm-plugin-suite/modules/media/vimeo-webinare/assets/css/style.css'
    );
}, 999);
```

### Problem: "Fortschritt wird nicht gespeichert"

**Ursache:** AJAX funktioniert nicht oder Nonce-Fehler

**Lösung:**
1. Browser-Konsole öffnen (F12)
2. Tab **Network**
3. Filter: **XHR**
4. Video abspielen
5. Suchen Sie nach `admin-ajax.php` Requests
6. Klicken Sie darauf → Tab **Response**
7. Sollte `{"success":true}` zeigen

**Falls Fehler oder kein Request:**
- JavaScript nicht geladen
- Vimeo Player API funktioniert nicht
- AJAX-URL falsch

### Problem: "Zertifikat wird nicht generiert"

**Ursache:** FPDF-Library nicht gefunden oder Schreibrechte fehlen

**Lösung:**
1. Prüfen Sie: `dgptm-plugin-suite/libraries/fpdf/fpdf.php` existiert?
2. Prüfen Sie Schreibrechte: `wp-content/uploads/webinar-certificates/`
3. Erstellen Sie Ordner manuell falls nötig

**Test:**
```php
// In wp-config.php temporär einfügen:
$fpdf_path = WP_CONTENT_DIR . '/plugins/dgptm-plugin-suite/libraries/fpdf/fpdf.php';
var_dump(file_exists($fpdf_path)); // Sollte bool(true) sein
```

## Support-Anfrage Checklist

Wenn Sie Support anfordern, liefern Sie bitte:

- [ ] WordPress Version
- [ ] PHP Version
- [ ] ACF Version
- [ ] Fehlermeldung (Screenshot)
- [ ] Browser-Konsole Errors (Screenshot)
- [ ] Debug Log Auszug
- [ ] Shortcode, den Sie verwenden
- [ ] Webinar Post-ID
- [ ] Vimeo Video ID (testweise)

**Debug-Informationen sammeln:**

```php
// Fügen Sie in wp-config.php ein:
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Dann auf der Webinar-Seite:
echo 'WordPress: ' . get_bloginfo('version') . '<br>';
echo 'PHP: ' . PHP_VERSION . '<br>';
echo 'ACF: ' . (defined('ACF_VERSION') ? ACF_VERSION : 'NICHT INSTALLIERT') . '<br>';
```

Kopieren Sie die Ausgabe und senden Sie sie mit Ihrer Support-Anfrage.

---

**Bei weiteren Fragen:** Kontaktieren Sie den DGPTM Support mit den gesammelten Debug-Informationen.
