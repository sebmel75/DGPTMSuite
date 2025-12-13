# Security Documentation

## Sicherheitsmaßnahmen in Version 4.0.0

Dieses Dokument beschreibt die implementierten Sicherheitsmaßnahmen im DGPTM Herzzentren Plugin.

## 🔒 Implementierte Sicherheitsfeatures

### 1. Input-Validierung

#### PHP-Seitige Validierung
```php
// Koordinaten-Validierung
private function sanitize_coordinate( $value, $default ) {
    $value = trim( $value );
    if ( empty( $value ) || ! is_numeric( $value ) ) {
        return $default;
    }
    return (float) $value;
}

// Integer-Validierung
$map_height = absint( $settings['map_height']['size'] );

// Post-ID-Validierung
$post_id = intval( $atts['post_id'] );
```

#### JavaScript-Seitige Validierung
```javascript
// Koordinaten-Prüfung
if (isNaN(lat) || isNaN(lng)) {
    console.error('Ungültige Koordinaten');
    return;
}

// Array-Prüfung
if (!Array.isArray(markers) || markers.length === 0) {
    return;
}
```

### 2. Output-Escaping

#### HTML-Escaping
```php
// Attribut-Escaping
esc_attr( $unique_id )
esc_attr( $map_height )
esc_attr( $latitude )

// HTML-Escaping
esc_html( $settings['label'] )
esc_html__( 'Text', 'domain' )

// URL-Escaping
esc_url( $post_url )
esc_url( DGPTM_HZ_URL . 'path' )

// JSON-Escaping
esc_attr( wp_json_encode( $data ) )
```

#### JavaScript HTML-Escaping
```javascript
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, function(m) { 
        return map[m]; 
    });
}
```

### 3. XSS-Schutz

#### Server-Seitig
```php
// wp_kses_post für HTML-Content
wp_kses_post( $post->post_title )
wp_kses_post( $anschrift )

// Sanitization Functions
sanitize_text_field()
sanitize_textarea_field()
sanitize_email()
sanitize_url()
```

#### Client-Seitig
```javascript
// Alle Benutzerdaten werden escaped
const safeTitle = escapeHtml(markerData.title || '');
const safeAddress = escapeHtml(markerData.address || '');
```

### 4. SQL-Injection-Schutz

```php
// WordPress Prepared Statements
$wpdb->prepare(
    "SELECT * FROM {$wpdb->posts} WHERE ID = %d",
    $post_id
);

// WordPress Query Functions
get_posts( array(
    'post_type' => 'herzzentrum',
    'post_status' => 'publish',
    'numberposts' => -1
) );
```

### 5. CSRF-Schutz (Nonce-Validierung)

```php
// Nonce erstellen
wp_create_nonce( 'dgptm_map_nonce' )

// Nonce prüfen
wp_verify_nonce( $_POST['nonce'], 'dgptm_map_nonce' )

// AJAX mit Nonce
wp_localize_script( 'dgptm-map-handler', 'dgptmMapConfig', array(
    'nonce' => wp_create_nonce( 'dgptm_map_nonce' ),
) );
```

### 6. Capability Checks

```php
// Berechtigungsprüfung vor Admin-Funktionen
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'Keine Berechtigung', 'dgptm-herzzentren' ) );
}

// Berechtigungsprüfung für Editor
if ( ! is_user_logged_in() ) {
    return '';
}

// Custom Capability Check
function hzb_user_can_edit_herzzentrum( $user_id, $post_id ) {
    // Implementierung...
}
```

### 7. Sichere File Uploads

```php
// Mime-Type-Prüfung
$allowed_types = array( 'image/jpeg', 'image/png', 'image/gif' );
if ( ! in_array( $file['type'], $allowed_types ) ) {
    return new WP_Error( 'invalid_type' );
}

// Filesize-Prüfung
$max_size = 5 * 1024 * 1024; // 5MB
if ( $file['size'] > $max_size ) {
    return new WP_Error( 'file_too_large' );
}
```

### 8. Sichere AJAX-Implementierung

```php
// AJAX-Handler registrieren
add_action( 'wp_ajax_dgptm_action', 'dgptm_ajax_handler' );
add_action( 'wp_ajax_nopriv_dgptm_action', 'dgptm_ajax_handler' );

function dgptm_ajax_handler() {
    // Nonce prüfen
    check_ajax_referer( 'dgptm_nonce', 'nonce' );
    
    // Berechtigung prüfen
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'Keine Berechtigung' );
    }
    
    // Daten validieren
    $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
    
    // Verarbeitung...
    
    // Sichere Antwort
    wp_send_json_success( $data );
}
```

## 🛡️ Best Practices

### 1. Niemals Benutzereingaben vertrauen

```php
// FALSCH ❌
$title = $_POST['title'];
echo $title;

// RICHTIG ✅
$title = isset( $_POST['title'] ) ? sanitize_text_field( $_POST['title'] ) : '';
echo esc_html( $title );
```

### 2. Immer Ausgaben escapen

```php
// FALSCH ❌
<div class="title"><?php echo $title; ?></div>

// RICHTIG ✅
<div class="title"><?php echo esc_html( $title ); ?></div>
```

### 3. Prepared Statements für Datenbank-Queries

```php
// FALSCH ❌
$wpdb->query( "DELETE FROM $wpdb->posts WHERE ID = " . $_POST['id'] );

// RICHTIG ✅
$wpdb->query( $wpdb->prepare(
    "DELETE FROM $wpdb->posts WHERE ID = %d",
    absint( $_POST['id'] )
) );
```

### 4. Capabilities prüfen

```php
// FALSCH ❌
if ( is_admin() ) {
    // Admin-Funktion
}

// RICHTIG ✅
if ( current_user_can( 'manage_options' ) ) {
    // Admin-Funktion
}
```

## 🔍 Security Audit Checklist

- [x] Input-Validierung implementiert
- [x] Output-Escaping implementiert
- [x] XSS-Schutz implementiert
- [x] SQL-Injection-Schutz implementiert
- [x] CSRF-Schutz (Nonces) implementiert
- [x] Capability Checks implementiert
- [x] Sichere File Uploads
- [x] Sichere AJAX-Implementierung
- [x] Keine Direkten Datenbankzugriffe
- [x] Keine eval() oder exec() Aufrufe
- [x] Keine PHP-Serialization von Benutzerdaten
- [x] Keine Directory-Traversal-Schwachstellen
- [x] Sichere Session-Handhabung
- [x] HTTPS-Unterstützung
- [x] Content Security Policy (CSP) kompatibel

## 🚨 Bekannte Sicherheitsrisiken

### Momentan: Keine bekannten kritischen Sicherheitsrisiken

## 📊 Security Testing

### Empfohlene Tools

1. **WPScan**: WordPress Security Scanner
   ```bash
   wpscan --url https://your-site.com --enumerate ap
   ```

2. **PHPStan**: PHP Static Analysis
   ```bash
   phpstan analyse --level=8 .
   ```

3. **ESLint**: JavaScript Linting
   ```bash
   eslint assets/js/*.js
   ```

4. **OWASP ZAP**: Web Application Security Testing

### Manuelle Tests

1. **XSS-Tests**:
   - Eingabe von `<script>alert('XSS')</script>` in alle Formularfelder
   - Prüfung ob Ausgabe escaped ist

2. **SQL-Injection-Tests**:
   - Eingabe von `' OR 1=1 --` in Suchfelder
   - Prüfung von Post-IDs mit manipulierten Werten

3. **CSRF-Tests**:
   - AJAX-Requests ohne gültigen Nonce
   - Formular-Submissions von externen Seiten

4. **Authentication-Tests**:
   - Zugriff auf Admin-Funktionen ohne Login
   - Zugriff mit niedrigeren Berechtigungen

## 🔐 Empfohlene Server-Konfiguration

### Apache .htaccess

```apache
# Plugin-Verzeichnis schützen
<Files "*.php">
    Order Deny,Allow
    Deny from all
    Allow from 127.0.0.1
</Files>

# Direkten Zugriff auf Include-Dateien verhindern
<FilesMatch "^(acf|admin|editor|ajax)\.php$">
    Order Deny,Allow
    Deny from all
</FilesMatch>

# Log-Dateien schützen
<FilesMatch "\.(log|txt)$">
    Order Deny,Allow
    Deny from all
</FilesMatch>
```

### Nginx

```nginx
# PHP-Dateien im Includes-Verzeichnis blockieren
location ~ ^/wp-content/plugins/dgptm-herzzentren-unified/includes/.*\.php$ {
    deny all;
}

# Log-Dateien blockieren
location ~ \.(log|txt)$ {
    deny all;
}
```

## 📝 Reporting Security Issues

Sicherheitslücken bitte **nicht öffentlich** melden!

**Kontakt für Security Reports:**
- Sebastian Melzer
- E-Mail: [Vertraulich]
- Erwartete Antwortzeit: 48 Stunden

**Beim Melden bitte angeben:**
1. Detaillierte Beschreibung der Schwachstelle
2. Schritte zur Reproduktion
3. Potentielle Auswirkungen
4. Vorgeschlagene Lösung (optional)

## 🔄 Security Updates

Das Plugin wird regelmäßig auf Sicherheitslücken überprüft:
- **Monatliche Überprüfung** von WordPress Core Updates
- **Wöchentliche Überprüfung** von Abhängigkeiten
- **Sofortige Patches** bei kritischen Sicherheitslücken

## 📚 Weitere Ressourcen

- [WordPress Plugin Security](https://developer.wordpress.org/plugins/security/)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- [Plugin Handbook - Security](https://developer.wordpress.org/plugins/security/)

---

**Letzte Aktualisierung**: 2025-10-27
**Version**: 4.0.0
**Status**: ✅ Secure
