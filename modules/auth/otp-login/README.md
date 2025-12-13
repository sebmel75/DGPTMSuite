# OTP Login Plugin v3.4.0 - Überarbeitete Version

## 🎯 Hauptverbesserungen

### 1. ✅ Enter-Taste funktioniert jetzt
- Drücken Sie Enter im E-Mail-Feld → Code wird gesendet
- Drücken Sie Enter im Code-Feld → Login wird ausgeführt
- Keine Seiten-Reloads mehr

### 2. ✨ Modernes, professionelles Design
- Schönere Buttons mit Gradient und Hover-Effekten
- Bessere Farbkodierung für Nachrichten (Erfolg/Fehler/Info)
- Loading-Animationen während API-Calls
- Responsive Design für mobile Geräte
- Verbesserte Typografie und Abstände

### 3. 🔒 Verbesserte Sicherheit
- **Versuchslimitierung**: Max. 5 Versuche pro OTP
- **Besseres Hashing**: wp_hash() statt md5()
- **Strikte Validierung**: Alle Eingaben werden validiert
- **URL-Validierung**: Webhook-URLs werden geprüft

### 4. 🎨 Bessere Benutzererfahrung
- Auto-Focus auf Eingabefeldern
- Code-Feld akzeptiert nur Zahlen
- Bessere Fehlermeldungen
- Platzhalterfür hilfreiche Hinweise

## 📦 Installation

1. **Plugin deaktivieren** (falls alte Version installiert)
2. **ZIP hochladen** über WordPress Admin → Plugins → Installieren
3. **Aktivieren**
4. **Fertig!** Alle Einstellungen bleiben erhalten

## 🚀 Verwendung

```
[dgptm_otp_login]
```

Mit Redirect:
```
[dgptm_otp_login redirect="https://example.com/dashboard"]
```

## ⚙️ Einstellungen

- **OTP Login → E-Mail & Sicherheit**: E-Mail-Templates und Rate-Limit
- **OTP Login → Preloader**: Preloader-Einstellungen
- **OTP Login → Anleitung**: Hilfe und Dokumentation

## 🔧 Technische Details

- **PHP**: >= 7.4
- **WordPress**: >= 5.8
- **Multisite**: ✅ Unterstützt
- **Text Domain**: dgptm

## 📝 Changelog

Siehe [CHANGELOG.md](CHANGELOG.md) für detaillierte Änderungen.

## 🛡️ Sicherheit

- CSRF-Schutz via WordPress Nonces
- Rate-Limiting pro IP + Identifier
- OTP wird gehasht gespeichert
- Max. 5 Versuche pro OTP
- Automatische Bereinigung nach Fehler

## 💡 Tipps

1. **Rate-Limit anpassen**: Standard ist 3 Versuche in 10 Minuten
2. **E-Mail-Template**: Anpassbar in den Einstellungen
3. **Webhook**: Optional für externe Integrationen
4. **WP-Login deaktivieren**: Für erhöhte Sicherheit

## 📞 Support

Bei Fragen oder Problemen:
- Autor: Sebastian Melzer
- Version: 3.4.0

## ✅ Alle Features erhalten

- ✅ OTP per E-Mail
- ✅ Login via E-Mail oder Benutzername
- ✅ "Angemeldet bleiben" (30 Tage)
- ✅ Rate-Limiting
- ✅ Preloader mit Logo
- ✅ Logout-Shortcodes
- ✅ WP-Login-Deaktivierung
- ✅ Multisite-Kompatibilität
- ✅ Webhook-Integration
- ✅ Anpassbare E-Mails
