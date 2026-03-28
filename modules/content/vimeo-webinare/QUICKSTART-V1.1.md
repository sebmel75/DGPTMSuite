# Quick Start Guide - Version 1.1

## 🚀 In 3 Minuten loslegen

### Schritt 1: Rewrite Rules aktivieren ⚙️

**WICHTIG:** Nach Installation/Update EINMAL ausführen!

```
WordPress Admin → Einstellungen → Permalinks → "Änderungen speichern" klicken
```

**Das war's!** Rewrite Rules sind jetzt aktiv.

---

### Schritt 2: Webinar erstellen 🎬

**Option A: Via Manager (Frontend)**

1. Öffnen Sie Ihre Manager-Seite (mit Shortcode `[vimeo_webinar_manager]`)
2. Klicken Sie **"Neues Webinar erstellen"**
3. Füllen Sie aus:
   - **Titel:** z.B. "Einführung in die Kardiologie"
   - **Vimeo Video ID:** z.B. `987654321` (nur Zahlen!)
   - **Erforderlicher Fortschritt:** `90` %
   - **EBCP Punkte:** `2.5`
   - **VNR:** (optional)
4. Klicken Sie **"Speichern"**

**Option B: Via WordPress Admin**

1. **Webinare → Neu hinzufügen**
2. Titel eingeben
3. Beschreibung (optional)
4. **Webinar Einstellungen:**
   - Vimeo Video ID: `987654321`
   - Erforderlicher Fortschritt: `90`
   - EBCP Punkte: `2.5`
5. **Veröffentlichen**

---

### Schritt 3: Webinar aufrufen 🎥

**Das Webinar ist SOFORT verfügbar!**

Wenn die Webinar-ID z.B. **123** ist:

```
https://ihre-domain.de/wissen/webinar/123
```

**Oder alternativ:**

```
https://ihre-domain.de/wissen/webinar?id=123
```

**Keine Seite erstellen nötig!** 🎉

---

## 📋 URLs im Überblick

### Dynamische Webinar-Seiten
```
/wissen/webinar/123   ← Webinar mit ID 123
/wissen/webinar/456   ← Webinar mit ID 456
/wissen/webinar/789   ← Webinar mit ID 789
```

### Shortcode-basierte Seiten

**Webinar-Liste:**
Erstellen Sie eine Seite mit:
```
[vimeo_webinar_liste]
```
→ Zeigt alle Webinare als Grid

**Manager:**
Erstellen Sie eine Seite mit:
```
[vimeo_webinar_manager]
```
→ CRUD-Interface für Manager

**Einzelnes Webinar (flexibel):**
Für spezielle Layouts:
```
[vimeo_webinar id="123"]
```
→ Webinar an beliebiger Stelle einbinden

---

## ✅ Checkliste

- [ ] Modul aktiviert (DGPTM Suite → Dashboard)
- [ ] Rewrite Rules geflusht (Einstellungen → Permalinks → Speichern)
- [ ] Test-Webinar erstellt
- [ ] URL getestet: `/wissen/webinar/{id}`
- [ ] Seite mit `[vimeo_webinar_liste]` erstellt
- [ ] Seite mit `[vimeo_webinar_manager]` erstellt (optional)

---

## 🎯 Typischer Workflow

### Als Manager:

1. **Webinar-Manager öffnen**
   ```
   /webinar-verwaltung (Ihre Manager-Seite)
   ```

2. **Neues Webinar erstellen**
   - Button "Neues Webinar erstellen"
   - Formular ausfüllen
   - Speichern

3. **Link teilen**
   ```
   https://ihre-domain.de/wissen/webinar/123
   ```

4. **Statistiken prüfen**
   - Statistik-Icon in der Tabelle klicken
   - Oder: Tab "Statistiken" öffnen

### Als Teilnehmer:

1. **Webinare durchsuchen**
   ```
   /webinare (Ihre Liste-Seite)
   ```

2. **Webinar öffnen**
   - Button "Jetzt ansehen" klicken
   - Wird zu `/wissen/webinar/123` weitergeleitet

3. **Video ansehen**
   - Fortschritt wird automatisch getrackt
   - Bei 90%: Abschluss + Fortbildungseintrag

4. **Zertifikat herunterladen**
   - Button "Zertifikat herunterladen"
   - PDF wird generiert und geöffnet

---

## 💡 Profi-Tipps

### Tipp 1: Direktlinks in E-Mails
Fügen Sie Webinar-Links direkt in E-Mails ein:
```
Sehr geehrte Damen und Herren,

Ihr Webinar "Kardiologie 2025" ist jetzt verfügbar:
https://ihre-domain.de/wissen/webinar/123

Mit freundlichen Grüßen
```

### Tipp 2: Menü-Navigation
Erstellen Sie ein Menü:
- "Webinare" → Ihre Liste-Seite
- "Manager" → Ihre Manager-Seite (nur für Manager sichtbar)

### Tipp 3: Vimeo-ID finden
1. Gehen Sie zu vimeo.com
2. Öffnen Sie Ihr Video
3. URL sieht so aus: `https://vimeo.com/987654321`
4. Die Zahlen (`987654321`) sind die ID

### Tipp 4: Embedding aktivieren
Vimeo-Einstellungen:
1. Video → Settings → Privacy
2. "Who can embed this video?" → **"Anyone"**
3. Speichern

---

## 🐛 Schnelle Problemlösung

### Problem: 404 bei /wissen/webinar/123
**Lösung:** Einstellungen → Permalinks → Speichern

### Problem: Login-Loop
**Lösung:** User ist nicht angemeldet → Normal! Anmelden erforderlich.

### Problem: "Vimeo Video ID fehlt"
**Lösung:** Webinar bearbeiten → Vimeo Video ID eintragen

### Problem: Video lädt nicht
**Lösung:**
1. Vimeo ID korrekt? (nur Zahlen)
2. Vimeo Embedding aktiviert?
3. Video auf "öffentlich" gesetzt?

---

## 📞 Hilfe

**Ausführliche Dokumentation:**
- `README.md` - Vollständige Feature-Dokumentation
- `UPDATE-V1.1.md` - Update-Guide & technische Details
- `DEBUGGING.md` - Troubleshooting-Guide

**Support:**
Kontaktieren Sie den DGPTM Support mit:
- WordPress Version
- PHP Version
- Fehlermeldung/Screenshot
- Webinar-ID

---

**Los geht's!** 🎬

Erstellen Sie Ihr erstes Webinar und teilen Sie den Link:
`/wissen/webinar/{id}`
