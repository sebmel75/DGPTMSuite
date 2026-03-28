# Update auf Version 1.1 - Dynamische URLs

## 🎉 Was ist neu?

**Version 1.1** führt dynamische URLs für Webinare ein. Keine manuellen Seiten mehr nötig!

### Alte Methode (v1.0):
```
❌ Für jedes Webinar eine Seite erstellen
❌ Shortcode manuell einfügen: [vimeo_webinar id="123"]
❌ Viele Seiten verwalten
```

### Neue Methode (v1.1):
```
✅ Webinare automatisch verfügbar unter /wissen/webinar/{id}
✅ Alternativ: /wissen/webinar?id={id}
✅ Keine manuellen Seiten mehr nötig
✅ Shortcode bleibt für flexible Einbindung
```

## 📋 Änderungen

### 1. Dynamische URL-Struktur

**Jedes Webinar ist automatisch erreichbar unter:**
- `/wissen/webinar/123` (sauber, SEO-freundlich)
- `/wissen/webinar?id=123` (alternative Query-String-Methode)

**Beispiel:**
```
Webinar mit ID 456:
https://ihre-domain.de/wissen/webinar/456
```

### 2. Automatische Template-Generierung

Die Webinar-Seite wird automatisch generiert mit:
- WordPress Header (Ihr Theme)
- Webinar Player
- WordPress Footer (Ihr Theme)

**Kein manuelles Erstellen von Seiten mehr nötig!**

### 3. Links in Liste & Manager

Die Webinar-Liste und der Manager verwenden jetzt automatisch die dynamischen URLs:

**Webinar-Liste:**
- "Jetzt ansehen" Button → `/wissen/webinar/{id}`

**Manager:**
- "Ansehen" Icon → `/wissen/webinar/{id}` (öffnet in neuem Tab)

### 4. Shortcode bleibt erhalten

Der Shortcode `[vimeo_webinar id="123"]` funktioniert weiterhin für:
- Einbindung in beliebige Seiten/Beiträge
- Custom Layouts
- Spezielle Landing Pages

## 🚀 Installation des Updates

### Schritt 1: Rewrite Rules aktivieren

Nach dem Update **MÜSSEN** Sie die Rewrite Rules neu laden:

**Option A: Via WordPress Admin**
1. WordPress Admin → **Einstellungen → Permalinks**
2. Klicken Sie einfach auf **Änderungen speichern** (ohne etwas zu ändern)
3. Fertig! Rewrite Rules sind aktualisiert

**Option B: Via Code (für Entwickler)**
```php
// Einmalig ausführen (z.B. in functions.php, dann wieder entfernen)
flush_rewrite_rules();
```

**Option C: Plugin deaktivieren/aktivieren**
1. DGPTM Suite → Dashboard
2. "Vimeo Webinare" deaktivieren
3. "Vimeo Webinare" aktivieren
4. Rewrite Rules werden automatisch aktualisiert

### Schritt 2: Alte Seiten bereinigen (optional)

Wenn Sie in v1.0 manuell Seiten für Webinare erstellt haben:

1. WordPress Admin → **Seiten**
2. Löschen Sie alle Webinar-Seiten (mit Shortcode `[vimeo_webinar id="..."]`)
3. Diese sind jetzt überflüssig, da Webinare dynamisch verfügbar sind

**WICHTIG:** Behalten Sie diese Seiten:
- Webinar-Liste Seite (mit `[vimeo_webinar_liste]`)
- Manager Seite (mit `[vimeo_webinar_manager]`)

### Schritt 3: Testen

Testen Sie die neue URL-Struktur:

1. **Erstellen Sie ein Test-Webinar** (oder verwenden Sie ein bestehendes)
2. Notieren Sie die Webinar-ID (z.B. 123)
3. Öffnen Sie im Browser:
   ```
   https://ihre-domain.de/wissen/webinar/123
   ```
4. **Erwartetes Verhalten:**
   - Login-Redirect falls nicht angemeldet
   - Webinar Player wird angezeigt
   - Fortschritts-Tracking funktioniert

## 🔧 URL-Struktur

### Standard-URL (Clean URLs)
```
/wissen/webinar/123
/wissen/webinar/456
/wissen/webinar/789
```

**Vorteile:**
- SEO-freundlich
- Sauber und kurz
- Keine Query-Strings

### Alternative URL (Query String)
```
/wissen/webinar?id=123
/wissen/webinar?id=456
```

**Vorteile:**
- Kompatibel mit allen Servern
- Funktioniert auch bei Permalink-Problemen

**Beide URLs führen zum gleichen Ergebnis!**

## 🎯 Use Cases

### Use Case 1: Manager erstellt Webinar

**Ablauf:**
1. Manager öffnet Frontend-Manager
2. Klickt "Neues Webinar erstellen"
3. Füllt Formular aus (Titel, Vimeo ID, etc.)
4. Speichert
5. **→ Webinar ist SOFORT verfügbar unter `/wissen/webinar/{id}`**

**Kein zusätzlicher Schritt nötig!**

### Use Case 2: Teilnehmer öffnet Webinar

**Ablauf:**
1. Teilnehmer öffnet Webinar-Liste
2. Klickt "Jetzt ansehen"
3. **→ Wird zu `/wissen/webinar/123` weitergeleitet**
4. Sieht Webinar Player
5. Fortschritt wird automatisch getrackt

### Use Case 3: Direkter Link teilen

Manager können jetzt **direkte Links** zu Webinaren teilen:

```
https://ihre-domain.de/wissen/webinar/123
```

**Empfänger:**
- Wird zum Login umgeleitet (falls nicht angemeldet)
- Sieht nach Login direkt das Webinar

### Use Case 4: Webinar in Custom Page einbinden

Wenn Sie ein Webinar an einer **speziellen Stelle** einbinden möchten:

**Erstellen Sie eine Seite:**
```
Seite: "Spezial-Webinar"
URL: /spezial-webinar

Inhalt:
Willkommen zu unserem Spezial-Webinar!

[vimeo_webinar id="123"]

Weitere Informationen...
```

**Shortcode funktioniert weiterhin!**

## 📊 Vorher/Nachher Vergleich

### Vorher (v1.0):

**Webinar erstellen:**
1. ✏️ Webinar anlegen (Admin)
2. ✏️ Seite erstellen (manuell)
3. ✏️ Shortcode einfügen `[vimeo_webinar id="123"]`
4. ✏️ Seite veröffentlichen
5. ✏️ Link notieren/teilen

**→ 5 Schritte**

### Nachher (v1.1):

**Webinar erstellen:**
1. ✏️ Webinar anlegen (Manager oder Admin)
2. ✅ **FERTIG!** Automatisch verfügbar

**→ 1 Schritt**

## 🔒 Sicherheit

### Login-Schutz

Alle dynamischen Webinar-URLs sind geschützt:

**Nicht angemeldete Benutzer:**
```
/wissen/webinar/123
→ Redirect zu: /wp-login.php?redirect_to=/wissen/webinar/123
→ Nach Login: Automatisch zurück zum Webinar
```

**Angemeldete Benutzer:**
```
/wissen/webinar/123
→ Webinar wird angezeigt
```

### 404-Handling

**Ungültige Webinar-ID:**
```
/wissen/webinar/999999 (existiert nicht)
→ WordPress 404-Seite: "Webinar nicht gefunden"
```

**Falsche Post-Type:**
```
/wissen/webinar/5 (ist eine Seite, kein Webinar)
→ WordPress 404-Seite: "Webinar nicht gefunden"
```

## 🛠️ Technische Details

### Rewrite Rules

Das Plugin registriert folgende Rewrite Rules:

```php
// Pattern 1: /wissen/webinar/{id}
'^wissen/webinar/([0-9]+)/?$'
→ index.php?vw_webinar_id=$matches[1]

// Pattern 2: /wissen/webinar?id={id}
'^wissen/webinar/?$'
→ index.php?vw_webinar_page=1
```

### Query Vars

Neue Query Variables:
- `vw_webinar_id` - Webinar Post ID
- `vw_webinar_page` - Flag für Webinar-Seite

### Template Rendering

```php
1. User öffnet /wissen/webinar/123
2. WordPress löst Rewrite Rule auf
3. Query Var 'vw_webinar_id' = 123
4. template_redirect Hook feuert
5. handle_webinar_page() wird aufgerufen
6. Webinar-Daten laden
7. get_header() - Ihr Theme Header
8. Player Template rendern
9. get_footer() - Ihr Theme Footer
10. exit
```

## 🐛 Troubleshooting

### Problem: 404 Fehler bei /wissen/webinar/123

**Ursache:** Rewrite Rules nicht aktualisiert

**Lösung:**
```
Einstellungen → Permalinks → Änderungen speichern
```

### Problem: Webinar-Liste zeigt alte Links

**Ursache:** Browser-Cache

**Lösung:**
```
Strg + F5 (Hard Reload)
Oder: Browser-Cache leeren
```

### Problem: Theme sieht komisch aus

**Ursache:** Theme erwartet bestimmte Page-Struktur

**Lösung:**
Fügen Sie in Ihr Theme CSS ein:
```css
.vw-page-wrapper {
    /* Ihr Theme-spezifisches Styling */
}
```

### Problem: Header/Footer fehlt

**Ursache:** Theme verwendet get_header()/get_footer() nicht standard

**Lösung:**
Bearbeiten Sie `render_webinar_template()` in der Hauptdatei

## 📝 Migration Guide

### Von v1.0 zu v1.1

**Schritt-für-Schritt:**

1. **Backup erstellen** (Datenbank + Dateien)

2. **Update durchführen**
   - Neue Dateien hochladen
   - Oder: Via DGPTM Suite Auto-Update

3. **Rewrite Rules flushen**
   ```
   Einstellungen → Permalinks → Speichern
   ```

4. **Test durchführen**
   ```
   /wissen/webinar/[eine-webinar-id]
   ```

5. **Alte Seiten löschen** (optional)
   - Seiten mit `[vimeo_webinar id="..."]` löschen

6. **Links aktualisieren** (falls externe Links vorhanden)
   - Alt: `/webinar-seite-123/`
   - Neu: `/wissen/webinar/123`

**Fertig!** 🎉

## 💡 Best Practices

### URL-Format wählen

**Empfohlen:**
```
/wissen/webinar/123 (Clean URL)
```

**Alternative (bei Permalink-Problemen):**
```
/wissen/webinar?id=123
```

### Webinar-Links teilen

**Intern (in WordPress):**
```php
$webinar = DGPTM_Vimeo_Webinare::get_instance();
$url = $webinar->get_webinar_url(123);
```

**Manuell (E-Mail, extern):**
```
https://ihre-domain.de/wissen/webinar/123
```

### Navigation einrichten

Erstellen Sie ein Menü:
- "Webinare" → Link zu Seite mit `[vimeo_webinar_liste]`
- Einzelne Webinare automatisch über die Liste zugänglich

## 🎓 FAQ

**Q: Funktioniert der alte Shortcode noch?**
A: Ja! `[vimeo_webinar id="123"]` funktioniert weiterhin.

**Q: Kann ich die URL-Struktur ändern?**
A: Ja, durch Anpassung der Rewrite Rules im Code.

**Q: Werden alte Seiten automatisch gelöscht?**
A: Nein, Sie müssen alte Webinar-Seiten manuell löschen.

**Q: Funktioniert es mit jedem Theme?**
A: Ja, solange das Theme `get_header()` und `get_footer()` nutzt.

**Q: Kann ich mehrere Webinare auf einer Seite zeigen?**
A: Ja, mit mehreren Shortcodes: `[vimeo_webinar id="1"] [vimeo_webinar id="2"]`

**Q: Werden Permalinks unterstützt?**
A: Ja, aber Sie müssen Rewrite Rules nach Update flushen.

---

**Bei Fragen:** Kontaktieren Sie den DGPTM Support

**Version:** 1.1.0
**Datum:** 2025-11-27
