# Wissens-Bot - KI-gestützter Wissensassistent für WordPress

Ein umfassendes WordPress-Plugin für einen KI-gestützten Wissens-Bot, der auf interne SharePoint-Dokumente, PubMed und Google Scholar zugreifen kann.

## Features

- 🤖 **Claude AI Integration** - Nutzt Claude Sonnet 4 für intelligente Antworten
- 📚 **SharePoint-Anbindung** - Durchsucht PDF-Dokumente in SharePoint
- 🔬 **PubMed-Integration** - Zugriff auf wissenschaftliche medizinische Literatur
- 🎓 **Google Scholar** - Durchsucht akademische Publikationen
- 🎯 **Themeneingrenzung** - Beschränkt Antworten auf definierte Themenbereiche
- 💬 **Chat-Interface** - Modernes, responsives Chat-Widget
- 🔐 **OAuth-Authentifizierung** - Sichere SharePoint-Verbindung

## Systemanforderungen

- WordPress 5.8 oder höher
- PHP 7.4 oder höher
- cURL-Erweiterung aktiviert
- DOMDocument-Erweiterung aktiviert (für XML/HTML-Parsing)

## Installation

### 1. Plugin hochladen

```bash
# Via FTP oder WordPress-Backend:
1. Laden Sie den Ordner 'wissens-bot' in `/wp-content/plugins/` hoch
2. Aktivieren Sie das Plugin im WordPress-Backend unter "Plugins"
```

### 2. Claude API Key erhalten

1. Besuchen Sie https://console.anthropic.com
2. Erstellen Sie ein Konto oder melden Sie sich an
3. Navigieren Sie zu "API Keys"
4. Erstellen Sie einen neuen API Key
5. Kopieren Sie den Key (beginnt mit "sk-ant-...")

### 3. SharePoint konfigurieren (optional)

#### Azure AD App-Registrierung:

1. Melden Sie sich im Azure Portal an: https://portal.azure.com
2. Navigieren Sie zu "Azure Active Directory" > "App registrations"
3. Klicken Sie auf "New registration"
   - Name: "Wissens-Bot"
   - Supported account types: "Accounts in this organizational directory only"
   - Redirect URI: Nicht erforderlich für Client Credentials Flow
4. Notieren Sie die **Application (client) ID** und **Directory (tenant) ID**

#### Client Secret erstellen:

1. In Ihrer App-Registrierung: "Certificates & secrets"
2. Klicken Sie auf "New client secret"
3. Beschreibung: "Wissens-Bot Secret"
4. Ablaufdatum: Wählen Sie entsprechend Ihrer Sicherheitsrichtlinien
5. Kopieren Sie den **Secret Value** (nur einmal sichtbar!)

#### API-Berechtigungen vergeben:

1. In Ihrer App-Registrierung: "API permissions"
2. Klicken Sie auf "Add a permission"
3. Wählen Sie "Microsoft Graph" > "Application permissions"
4. Fügen Sie folgende Berechtigungen hinzu:
   - `Sites.Read.All` - Zum Lesen von SharePoint-Sites
   - `Files.Read.All` - Zum Lesen von Dateien
5. Klicken Sie auf "Grant admin consent"

#### SharePoint Site URL ermitteln:

```
Format: https://[ihr-tenant].sharepoint.com/sites/[site-name]
Beispiel: https://contoso.sharepoint.com/sites/wissensbot
```

## Konfiguration

### 1. Plugin-Einstellungen öffnen

Im WordPress-Backend: **Wissens-Bot** > **Einstellungen**

### 2. Claude AI konfigurieren

- **Claude API Key**: Ihr Anthropic API Key
- **Max Tokens**: 4000 (empfohlen für detaillierte Antworten)
- **System Prompt**: 
  ```
  Du bist ein Experte für Perfusionstechnologie und Extrakorporale Zirkulation. 
  Beantworte nur Fragen zu den konfigurierten Themenbereichen. 
  Nutze die bereitgestellten Dokumente als Wissensgrundlage und zitiere deine Quellen.
  ```

### 3. Themeneingrenzung

Geben Sie die erlaubten Themenbereiche kommagetrennt ein:

```
Perfusiologie, Herz-Lungen-Maschine, IABP, ECLS, ECMO, Kardiotechnik, Oxygenator, Kardioplegie
```

### 4. Datenquellen aktivieren

- ☑️ **SharePoint aktivieren** - Für interne Dokumente
- ☑️ **PubMed aktivieren** - Für wissenschaftliche Artikel
- ☑️ **Google Scholar aktivieren** - Für akademische Publikationen

### 5. SharePoint-Konfiguration

- **Tenant ID**: Ihre Azure AD Tenant ID
- **Client ID**: Die Application (client) ID
- **Client Secret**: Der generierte Client Secret
- **Site URL**: `https://ihr-tenant.sharepoint.com/sites/ihr-site`
- **Ordner-Pfade** (ein Pfad pro Zeile):
  ```
  /Shared Documents/Wissensdatenbank
  /Dokumente/Perfusiologie
  /Shared Documents/Fortbildungen
  ```

## Verwendung

### Shortcode einfügen

Fügen Sie den Bot auf einer beliebigen Seite oder in einem Beitrag ein:

```
[wissens_bot]
```

**Mit Optionen:**

```
[wissens_bot title="Perfusions-Assistent" height="700px"]
```

### PHP-Integration

```php
<?php
// In Theme-Templates
echo do_shortcode('[wissens_bot]');
?>
```

### Widget

Das Plugin kann auch als Widget in Widget-Bereichen verwendet werden:

```php
// In functions.php
add_action('widgets_init', function() {
    register_sidebar(array(
        'name' => 'Chat Widget Area',
        'id' => 'chat-widget',
    ));
});

// Im Template
<?php dynamic_sidebar('chat-widget'); ?>
```

## Erweiterte Konfiguration

### Google Scholar Optimierung

Google Scholar hat keine offizielle API. Das Plugin bietet zwei Methoden:

#### Option 1: SerpAPI (Empfohlen)

1. Registrieren Sie sich bei https://serpapi.com
2. Holen Sie sich einen API Key
3. Fügen Sie in `wp-config.php` hinzu:
   ```php
   define('WISSENS_BOT_SERPAPI_KEY', 'ihr-serpapi-key');
   ```

#### Option 2: Semantic Scholar (Kostenlos)

Das Plugin nutzt automatisch die Semantic Scholar API als Fallback. Keine Konfiguration erforderlich.

### PDF-Text-Extraktion optimieren

Für bessere PDF-Extraktion installieren Sie `pdftotext`:

```bash
# Ubuntu/Debian
sudo apt-get install poppler-utils

# macOS
brew install poppler
```

### Performance-Optimierung

#### Caching aktivieren

Fügen Sie in `wp-config.php` hinzu:

```php
// SharePoint Token-Cache verlängern
define('WISSENS_BOT_CACHE_DURATION', 3600); // 1 Stunde
```

#### Rate Limiting

Um API-Kosten zu kontrollieren:

```php
// Max. Anfragen pro Benutzer pro Stunde
define('WISSENS_BOT_RATE_LIMIT', 50);
```

## Troubleshooting

### Problem: "Claude API Key nicht konfiguriert"

**Lösung:** Prüfen Sie, ob der API Key korrekt unter **Wissens-Bot** > **Einstellungen** eingetragen ist.

### Problem: SharePoint-Verbindung schlägt fehl

**Lösungsschritte:**

1. Prüfen Sie Tenant ID, Client ID und Client Secret
2. Verifizieren Sie API-Berechtigungen in Azure AD
3. Stellen Sie sicher, dass Admin Consent erteilt wurde
4. Aktivieren Sie WordPress Debug:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```
5. Prüfen Sie `/wp-content/debug.log` für Fehlermeldungen

### Problem: Keine Dokumente gefunden

**Lösungsschritte:**

1. Prüfen Sie die Ordner-Pfade (Groß-/Kleinschreibung beachten)
2. Stellen Sie sicher, dass PDFs im Ordner vorhanden sind
3. Verifizieren Sie Leseberechtigungen für die App
4. Testen Sie die Graph API manuell:
   ```
   https://graph.microsoft.com/v1.0/sites/{host}:/{site-path}:/drive/root/children
   ```

### Problem: Bot antwortet nicht auf Fragen

**Mögliche Ursachen:**

1. Frage ist nicht themenrelevant → Keywords anpassen
2. API-Limit erreicht → Prüfen Sie Ihr Anthropic-Dashboard
3. Timeout-Probleme → `max_execution_time` in PHP erhöhen

## Sicherheit

### Best Practices

1. **API Keys schützen**
   - Niemals in öffentlichen Repositories committen
   - Verwenden Sie Environment Variables in Produktion

2. **SharePoint-Berechtigungen minimieren**
   - Vergeben Sie nur die minimal notwendigen Rechte
   - Verwenden Sie separate Apps für verschiedene Umgebungen

3. **Rate Limiting implementieren**
   - Schützen Sie vor übermäßiger Nutzung
   - Implementieren Sie User-basierte Limits

4. **HTTPS verwenden**
   - Stellen Sie sicher, dass Ihre WordPress-Site HTTPS verwendet
   - Besonders wichtig für API-Kommunikation

## Entwicklung

### Dateistruktur

```
wissens-bot/
├── wissens-bot.php              # Hauptplugin-Datei
├── includes/
│   ├── class-admin-settings.php  # Admin-Interface
│   ├── class-api-handler.php     # Claude API & Koordination
│   ├── class-sharepoint-connector.php
│   ├── class-pubmed-connector.php
│   ├── class-scholar-connector.php
│   └── class-chat-frontend.php   # Frontend-Widget
├── assets/
│   ├── css/
│   │   ├── style.css            # Frontend-Styling
│   │   └── admin.css            # Admin-Styling
│   └── js/
│       └── chat.js              # Chat-Funktionalität
└── README.md
```

### Hooks & Filter

Das Plugin bietet folgende Hooks für Erweiterungen:

```php
// Filter: System Prompt anpassen
add_filter('wissens_bot_system_prompt', function($prompt, $context_data) {
    return $prompt . "\nZusätzliche Anweisungen...";
}, 10, 2);

// Filter: Themenrelevanz prüfen
add_filter('wissens_bot_is_topic_relevant', function($is_relevant, $message) {
    // Custom-Logik
    return $is_relevant;
}, 10, 2);

// Action: Nach erfolgreicher Antwort
add_action('wissens_bot_after_response', function($message, $response) {
    // Logging, Analytics, etc.
}, 10, 2);
```

## Kosten

### Anthropic Claude API

- Pricing: https://www.anthropic.com/pricing
- Claude Sonnet 4: ~$3 per MTok Input, ~$15 per MTok Output
- Durchschnittliche Kosten pro Chat-Interaktion: $0.01 - $0.05

### Optionale Dienste

- **SerpAPI**: Ab $50/Monat für 5.000 Suchanfragen
- **SharePoint**: Teil von Microsoft 365 (keine zusätzlichen Kosten)
- **PubMed**: Kostenlos
- **Semantic Scholar**: Kostenlos

## Support & Kontakt

- **DGPTM**: https://dgptm.de
- **Entwickler**: Sebastian
- **Issues**: Bei technischen Problemen

## Lizenz

GPL v2 or later

## Changelog

### Version 1.0.0 (2025-10-29)
- Erste Veröffentlichung
- Claude Sonnet 4 Integration
- SharePoint, PubMed und Google Scholar Anbindung
- Chat-Interface mit Conversation History
- Themeneingrenzung
- Quellenangaben

---

**Hinweis**: Dieses Plugin ist speziell für medizinische und wissenschaftliche Organisationen entwickelt worden, insbesondere für Perfusionstechnologie und Extrakorporale Zirkulation.
