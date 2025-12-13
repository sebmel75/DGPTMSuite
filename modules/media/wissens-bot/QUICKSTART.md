# Wissens-Bot - Schnellstart-Anleitung

## 🚀 In 5 Minuten einsatzbereit

### Schritt 1: Plugin installieren

```bash
1. Laden Sie das Plugin-Verzeichnis nach /wp-content/plugins/ hoch
2. Im WordPress-Backend: Plugins → Wissens-Bot → Aktivieren
```

### Schritt 2: Claude API Key holen

1. Besuchen Sie: https://console.anthropic.com
2. Erstellen Sie ein Konto
3. Navigieren Sie zu "API Keys"
4. Erstellen Sie einen neuen Key
5. Kopieren Sie den Key (beginnt mit `sk-ant-...`)

### Schritt 3: Basis-Konfiguration

Im WordPress-Backend: **Wissens-Bot** → **Einstellungen**

**Pflichtfelder:**

```
Claude API Key: [Ihr API Key]
Max Tokens: 4000
Themen-Keywords: Perfusiologie, Herz-Lungen-Maschine, IABP, ECLS, ECMO
```

**Datenquellen (für den Start):**

- ✅ PubMed aktivieren
- ⬜ SharePoint aktivieren (später konfigurieren)
- ⬜ Google Scholar aktivieren (später konfigurieren)

Klicken Sie auf **"Einstellungen speichern"**

### Schritt 4: Bot auf Seite einfügen

Erstellen Sie eine neue Seite:

```
Titel: Wissens-Bot
Inhalt: [wissens_bot]
```

Veröffentlichen → Seite ansehen

### ✅ Fertig!

Ihr Wissens-Bot ist jetzt einsatzbereit. Testen Sie ihn mit einer Frage wie:

```
"Was ist eine Herz-Lungen-Maschine?"
```

---

## 📚 Erweiterte Konfiguration (Optional)

### SharePoint-Anbindung

Benötigt etwa 15 Minuten Setup-Zeit.

**Voraussetzungen:**
- Microsoft 365 / Azure AD Zugang mit Admin-Rechten
- SharePoint-Site mit Dokumenten

**Schritte:**

1. **Azure AD App erstellen:**
   - https://portal.azure.com → Azure Active Directory
   - App registrations → New registration
   - Name: "Wissens-Bot"
   - Notieren Sie: Tenant ID, Client ID

2. **Client Secret erstellen:**
   - Certificates & secrets → New client secret
   - Kopieren Sie den Secret Value

3. **Berechtigungen vergeben:**
   - API permissions → Microsoft Graph → Application permissions
   - Hinzufügen: `Sites.Read.All`, `Files.Read.All`
   - "Grant admin consent" klicken

4. **In WordPress konfigurieren:**
   ```
   Tenant ID: [Ihre Tenant ID]
   Client ID: [Ihre Client ID]
   Client Secret: [Ihr Secret]
   Site URL: https://ihr-tenant.sharepoint.com/sites/ihr-site
   Ordner-Pfade: /Shared Documents/Wissen
   ```

5. **SharePoint aktivieren** ✅

### Google Scholar mit SerpAPI

**Kostenlos-Variante:**
- Plugin nutzt automatisch Semantic Scholar (kostenlos)
- Keine weitere Konfiguration nötig

**Premium-Variante (empfohlen):**
1. Registrieren Sie sich: https://serpapi.com
2. Holen Sie einen API Key
3. In `wp-config.php` einfügen:
   ```php
   define('WISSENS_BOT_SERPAPI_KEY', 'ihr-key');
   ```

---

## 🎨 Anpassungen

### Custom Styling

Fügen Sie eigenes CSS hinzu:

```css
/* In Ihrem Theme oder Custom CSS Plugin */
.wissens-bot-container {
    max-width: 1000px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}

.wissens-bot-header {
    background: linear-gradient(135deg, #your-color1, #your-color2);
}
```

### Mehrere Bots mit unterschiedlichen Themen

```
[wissens_bot title="Perfusions-Experte" height="600px"]
[wissens_bot title="Kardiotechnik-Assistent" height="700px"]
```

Passen Sie die Themen-Keywords in den Einstellungen an.

---

## 🐛 Häufige Probleme

### "Claude API Key nicht konfiguriert"

→ Überprüfen Sie die Einstellungsseite. Key muss mit `sk-ant-` beginnen.

### Bot antwortet nicht

1. Prüfen Sie Browser-Konsole (F12) auf Fehler
2. Aktivieren Sie WordPress Debug:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```
3. Prüfen Sie `/wp-content/debug.log`

### SharePoint findet keine Dokumente

1. Ordner-Pfad korrekt? (Groß-/Kleinschreibung!)
2. Admin Consent erteilt?
3. Sind PDFs im Ordner?

---

## 💡 Tipps

### Optimale Themen-Keywords

```
Allgemein: Perfusiologie, Kardiotechnik, ECMO, ECLS
Spezifisch: Herz-Lungen-Maschine, IABP, Oxygenator, Kardioplegie
Verwandt: Herzchirurgie, Extrakorporal, Bypass
```

### System Prompt Beispiele

**Konservativ (präzise Antworten):**
```
Du bist ein Experte für Perfusionstechnologie. 
Antworte präzise und wissenschaftlich korrekt. 
Zitiere immer deine Quellen.
```

**Freundlich (für Patienten):**
```
Du bist ein freundlicher Assistent, der komplexe 
medizinische Themen verständlich erklärt.
Verwende einfache Sprache und Analogien.
```

**Streng (nur definierte Themen):**
```
Du darfst NUR Fragen zu Perfusionstechnologie beantworten.
Bei allen anderen Themen lehne höflich ab.
```

---

## 📊 Kosten-Übersicht

### Claude API (Anthropic)
- Erste 5$ kostenlos (Credit)
- Danach: ~$0.01-0.05 pro Chat-Interaktion
- 1000 Fragen ≈ $10-50

### Optionale Dienste
- SharePoint: Teil von M365 (keine Extra-Kosten)
- PubMed: Kostenlos
- SerpAPI: $50/Monat für 5.000 Suchen
- Semantic Scholar: Kostenlos

---

## 📞 Support

Bei Problemen:
1. Prüfen Sie die ausführliche README.md
2. Aktivieren Sie Debug-Modus
3. Kontaktieren Sie Ihren Administrator

---

**Viel Erfolg mit Ihrem Wissens-Bot! 🚀**
