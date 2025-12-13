# Changelog - OTP Login Plugin v3.4.0

## Neue Funktionen & Verbesserungen

### ✅ Enter-Taste-Unterstützung
- **Schritt 1**: Enter-Taste im E-Mail/Benutzername-Feld sendet den Code
- **Schritt 2**: Enter-Taste im OTP-Code-Feld führt den Login aus
- Verhindert Seitenreload durch `preventDefault()`

### ✨ Modernes Design
- **Neue Farbpalette**: Moderner blauer Gradient für Buttons
- **Verbesserte Typografie**: Bessere Lesbarkeit und Abstände
- **Hover-Effekte**: Interaktive Button-Animationen
- **Fokus-Zustände**: Klare visuelle Rückmeldung bei Eingabefeldern (blaue Umrandung mit Schatten)
- **Responsive Design**: Optimiert für mobile Geräte (< 480px)
- **Loading-Animationen**: Rotierende Spinner während API-Calls
- **Farbcodierte Nachrichten**:
  - Erfolg: Grün
  - Fehler: Rot
  - Info: Blau
- **Professionelle Schatten**: Dezente Box-Shadows für mehr Tiefe

### 🔒 Sicherheitsverbesserungen
1. **Rate-Limit-Verbesserung**:
   - Verwendet jetzt `wp_hash()` statt `md5()` für bessere Sicherheit
   
2. **Versuchslimitierung**:
   - Max. 5 Versuche pro OTP
   - Automatisches Löschen des OTP nach 5 fehlgeschlagenen Versuchen
   - Tracking der Versuche in `_dgptm_otp_attempts` User-Meta
   
3. **Verbesserte Input-Validierung**:
   - `sanitize_email()` für E-Mail-Adressen
   - `sanitize_user()` für Benutzernamen
   - Strikte Regex-Prüfung für OTP (nur 6 Ziffern)
   - Auto-Format des OTP-Feldes (nur Zahlen erlaubt)
   
4. **URL-Validierung**:
   - Webhook-URLs werden jetzt mit `filter_var()` validiert
   
5. **Fehlerbehandlung**:
   - Bessere Netzwerk-Fehlerbehandlung im JavaScript
   - Try-Catch für alle AJAX-Calls

### 🎨 UX-Verbesserungen
- **Auto-Focus**: Automatischer Fokus auf relevantes Eingabefeld
- **Code-Formatierung**: OTP-Code zentriert mit Letter-Spacing
- **Button-States**: Disabled-State während API-Calls
- **Bessere Nachrichten**: Klarere Fehlermeldungen
- **Platzhalter**: Hilfreiche Placeholder-Texte
- **Select on Error**: Code-Feld wird bei Fehler automatisch markiert

### 🛠️ Code-Qualität
- **Strikte Eingabevalidierung** vor jedem API-Call
- **Konsistente Fehlerbehandlung**
- **Verbesserte Code-Struktur** mit separaten Funktionen
- **Bessere Kommentierung**
- **ES5-Kompatibilität** für ältere Browser

## Erhaltene Funktionen
✅ Alle bisherigen Features bleiben voll funktionsfähig:
- AJAX-basiertes OTP-Login
- E-Mail- oder Benutzername-Login
- 30-Tage "Angemeldet bleiben"
- Rate-Limiting
- Preloader mit rotierendem Logo
- Logout-Shortcodes
- WP-Login-Deaktivierung
- Multisite-Kompatibilität
- Webhook-Integration (optional)
- Anpassbare E-Mail-Templates

## Technische Details
- **Version**: 3.4.0
- **PHP**: >= 7.4
- **WordPress**: >= 5.8
- **Getestet bis**: WordPress 6.4
- **Kompatibilität**: Multisite ✅

## Installation
1. Altes Plugin deaktivieren
2. Neues Plugin hochladen und aktivieren
3. Einstellungen bleiben erhalten (Network-Settings-kompatibel)

## Sicherheitshinweise
- OTP-Codes werden gehasht gespeichert (wp_hash_password)
- Rate-Limiting pro IP + Identifier
- Keine OTP-Übertragung in Webhooks
- CSRF-Schutz via Nonces
- Attempt-Limiting auf OTP-Ebene
