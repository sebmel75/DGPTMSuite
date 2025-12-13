# Screenshots für Abstimmen-Addon Anleitung

Diese Datei listet alle benötigten Screenshots für die vollständige Dokumentation des Abstimmen-Addons auf.

## 1. Übersicht & Dashboard

### 1.1 Umfragen-Übersicht
**Dateiname:** `umfragen-uebersicht.png`
**Beschreibung:** Hauptseite mit Umfragen-Liste
**Zeigt:**
- Aktive und archivierte Umfragen
- Toggle-Schalter für Status
- Neu-Button
- Name, Erstellt-Datum, Status-Spalten
- "Fragen verwalten" Links

**Shortcode:** `[manage_poll]`

---

### 1.2 Neue Umfrage erstellen
**Dateiname:** `umfrage-erstellen.png`
**Beschreibung:** Modal-Dialog zum Erstellen einer Umfrage
**Zeigt:**
- Umfrage-Name Eingabefeld
- "Teilnehmer müssen sich anmelden" Checkbox
- Logo-URL (optional)
- Speichern / Abbrechen Buttons

---

### 1.3 Fragen verwalten
**Dateiname:** `fragen-verwalten.png`
**Beschreibung:** Fragen-Übersicht einer Umfrage
**Zeigt:**
- Liste aller Fragen
- Status (Aktiv/Archiviert)
- "Freigegeben" Toggle
- Bearbeiten/Löschen Aktionen
- "Neue Frage" Button

**Shortcode:** `[manage_questions poll_id="123"]`

---

### 1.4 Frage bearbeiten
**Dateiname:** `frage-bearbeiten.png`
**Beschreibung:** Frage-Editor Modal
**Zeigt:**
- Fragetext
- Antwortmöglichkeiten (mehrere Zeilen)
- Max. Stimmen pro Teilnehmer
- Chart-Typ (Balken/Torte)
- Status (Aktiv/Archiviert)
- Freigabe-Toggle

---

## 2. Voting-Interface (Teilnehmer-Ansicht)

### 2.1 Login mit Abstimmungs-Code
**Dateiname:** `voting-login.png`
**Beschreibung:** Login-Formular für Teilnehmer
**Zeigt:**
- 6-stelliges Code-Eingabefeld
- "Abstimmen" Button
- Fehlermeldung bei ungültigem Code

**Shortcode:** `[voting_login]`

---

### 2.2 Aktive Abstimmung
**Dateiname:** `voting-aktiv.png`
**Beschreibung:** Teilnehmer sieht freigegebene Frage
**Zeigt:**
- Fragetext
- Antwortmöglichkeiten als Buttons/Checkboxen
- "Stimme abgeben" Button
- Max. Stimmen Hinweis (bei Mehrfachauswahl)

**Shortcode:** `[voting_interface]`

---

### 2.3 Voting erfolgreich
**Dateiname:** `voting-erfolg.png`
**Beschreibung:** Bestätigung nach erfolgreicher Stimmabgabe
**Zeigt:**
- "Ihre Stimme wurde erfasst" Nachricht
- Grüner Haken/Checkmark
- Hinweis auf Beamer für Ergebnisse

---

### 2.4 Keine aktive Abstimmung
**Dateiname:** `voting-warten.png`
**Beschreibung:** Warteansicht wenn keine Frage freigegeben
**Zeigt:**
- "Bitte warten..." Nachricht
- Hinweis, dass der Moderator noch keine Frage freigegeben hat

---

## 3. Beamer-Ansicht (Projektion)

### 3.1 Beamer - Live Ergebnisse (Balkendiagramm)
**Dateiname:** `beamer-live-balken.png`
**Beschreibung:** Live-Ergebnisse als Balkendiagramm
**Zeigt:**
- Fragetext oben
- Balkendiagramm (Chart.js)
- Anzahl Stimmen pro Antwort
- Live-Update Animation

**Shortcode:** `[beamer_view mode="live"]`

---

### 3.2 Beamer - Finale Ergebnisse (Tortendiagramm)
**Dateiname:** `beamer-final-torte.png`
**Beschreibung:** Finale Ergebnisse als Tortendiagramm
**Zeigt:**
- Fragetext
- Tortendiagramm mit Prozentangaben
- Legende mit Farben
- Gesamtzahl Stimmen

**Shortcode:** `[beamer_view mode="final"]`

---

### 3.3 Beamer - Warteansicht
**Dateiname:** `beamer-warten.png`
**Beschreibung:** Beamer wenn keine Frage aktiv
**Zeigt:**
- Logo (falls konfiguriert)
- "Abstimmung startet in Kürze..." Text
- Minimalistisches Design

---

## 4. Zoom-Integration

### 4.1 Zoom-Einstellungen
**Dateiname:** `zoom-einstellungen.png`
**Beschreibung:** Admin-Einstellungen für Zoom
**Zeigt:**
- Account ID, Client ID, Client Secret Felder
- Meeting/Webinar ID
- Typ-Auswahl (Meeting/Webinar)
- Webhook Secret Token
- "Verbindung testen" Button

**Admin-Seite:** DGPTM Abstimmen → Zoom Einstellungen

---

### 4.2 Zoom-Registrierung
**Dateiname:** `zoom-registrierung.png`
**Beschreibung:** Teilnehmer-Registrierung bei Zoom
**Zeigt:**
- Liste aller WordPress-Benutzer
- Status: "Nicht registriert" / "Registriert" / "Ausstehend"
- Join-URL (wenn registriert)
- "Registrieren" Buttons
- Batch-Aktionen (Alle registrieren)

**Shortcode:** `[zoom_registration]`

---

### 4.3 Zoom-Teilnehmerliste
**Dateiname:** `zoom-teilnehmer.png`
**Beschreibung:** Live-Teilnehmerliste vom Zoom Meeting
**Zeigt:**
- Aktuell verbundene Teilnehmer
- Join-Zeit
- Gesamtdauer
- "Live aktualisieren" Animation

**Shortcode:** `[zoom_participants]`

---

## 5. Anwesenheitserfassung

### 5.1 Anwesenheitsliste
**Dateiname:** `anwesenheit-liste.png`
**Beschreibung:** Vollständige Anwesenheitsliste
**Zeigt:**
- Name, Email, Status-Spalten
- Mitgliedsart, Mitgliedsnummer
- Join-Zeit, Leave-Zeit, Gesamtdauer
- "Manuell erfasst" Badge
- Export-Buttons (CSV, Excel)

**Shortcode:** `[attendance_list id="meeting_id"]`

---

### 5.2 Anwesenheits-Scanner
**Dateiname:** `scanner-interface.png`
**Beschreibung:** QR-Code Scanner Interface
**Zeigt:**
- QR-Code Reader Bereich
- "Kamera starten" Button
- Manuelle Suche Eingabefeld
- Live-Scan Feedback (grüner Flash)

**Shortcode:** `[presence_scanner id="meeting_id"]`

---

### 5.3 Manuelle Suche
**Dateiname:** `scanner-suche.png`
**Beschreibung:** Manuelle Teilnehmersuche
**Zeigt:**
- Suchfeld für Namen
- Suchergebnisse (aus Zoho CRM)
- Details: Name, Email, Mitgliedsart
- "Hinzufügen" Buttons
- Suchergebnis-Karten

---

### 5.4 Scanner Erfolg
**Dateiname:** `scanner-erfolg.png`
**Beschreibung:** Bestätigung nach erfolgreichem Scan
**Zeigt:**
- Grüner Vollbild-Flash
- "Teilnehmer erfasst: [Name]" Nachricht
- Automatisches Zurücksetzen nach 2 Sekunden

---

## 6. Admin-Verwaltung

### 6.1 Abstimmungs-Dashboard
**Dateiname:** `admin-dashboard.png`
**Beschreibung:** Haupt-Dashboard für Administratoren
**Zeigt:**
- Statistiken (Anzahl Umfragen, aktive Fragen, Teilnehmer)
- Schnellzugriffe zu häufigen Aktionen
- Letzte Aktivitäten

---

### 6.2 Benutzer-Verwaltung
**Dateiname:** `benutzer-verwaltung.png`
**Beschreibung:** User-Management für Abstimmungen
**Zeigt:**
- Liste aller Benutzer
- Voting-Status (ON/OFF) Toggle
- 6-stellige Codes
- "Codes neu generieren" Button
- "Manager" Toggle

**Shortcode:** `[manage_users]`

---

### 6.3 Code-Generierung
**Dateiname:** `code-generation.png`
**Beschreibung:** Codes für alle Teilnehmer generieren
**Zeigt:**
- "Alle Codes neu generieren" Button
- Bestätigungs-Dialog
- Liste generierter Codes
- CSV-Export Option

---

## 7. Fehler & Edge Cases

### 7.1 Fehler - Ungültiger Code
**Dateiname:** `fehler-code.png`
**Beschreibung:** Fehlermeldung bei ungültigem Code
**Zeigt:**
- Rote Fehlermeldung
- "Code ungültig oder bereits verwendet"
- Eingabefeld rot umrandet

---

### 7.2 Fehler - Zoom-Verbindung
**Dateiname:** `fehler-zoom.png`
**Beschreibung:** Zoom-Verbindungsfehler
**Zeigt:**
- Fehlermeldung "Verbindung zu Zoom fehlgeschlagen"
- Hinweis auf Credentials-Prüfung
- "Erneut versuchen" Button

---

### 7.3 Fehler - Keine Berechtigung
**Dateiname:** `fehler-berechtigung.png`
**Beschreibung:** Zugriff verweigert
**Zeigt:**
- "Sie haben keine Berechtigung" Nachricht
- Hinweis an Administrator wenden

---

## 8. Mobile Ansichten

### 8.1 Mobile - Voting Interface
**Dateiname:** `mobile-voting.png`
**Beschreibung:** Abstimmung auf Mobilgerät
**Zeigt:**
- Responsive Design
- Touch-optimierte Buttons
- Lesbare Schriftgrößen

---

### 8.2 Mobile - Scanner
**Dateiname:** `mobile-scanner.png`
**Beschreibung:** Scanner auf Smartphone
**Zeigt:**
- Kamera-Interface
- QR-Code Rahmen
- "Scan erfolgreich" Animation

---

## Screenshot-Anforderungen

### Technische Spezifikationen
- **Format:** PNG mit Transparenz (wo sinnvoll)
- **Auflösung:** Mindestens 1920x1080px (Desktop), 375x667px (Mobile)
- **DPI:** 144 DPI für scharfe Darstellung
- **Dateigrößen:** < 500KB pro Screenshot (optimiert)

### Inhaltliche Anforderungen
- **Testdaten:** Verwende realistische, aber anonymisierte Daten
- **Sprache:** Deutsch (DGPTM ist deutsche Organisation)
- **Branding:** DGPTM Logo wo angebracht
- **Konsistenz:** Gleiche Farbschema und Styling überall

### Erfassungs-Hinweise
- **Browser:** Verwende Chrome/Firefox neueste Version
- **Zoom:** 100% (keine Browser-Vergrößerung)
- **Fenster:** Vollbild oder definierte Größe (z.B. 1920x1080)
- **UI-Elemente:** Alle wichtigen Elemente sichtbar
- **Hover-States:** Wo relevant, Hover-Zustände zeigen

## Verwendung in README.md

Screenshots werden in README.md eingebunden mit:

```markdown
![Beschreibung](screenshots/dateiname.png)
```

**Beispiel:**
```markdown
### Umfragen-Übersicht

![Umfragen-Übersicht](screenshots/umfragen-uebersicht.png)

Die Umfragen-Übersicht zeigt alle aktiven und archivierten Umfragen...
```

## Nächste Schritte

1. **Screenshots erstellen:**
   - WordPress-Installation mit aktiviertem Abstimmen-Addon
   - Testdaten anlegen (Umfragen, Fragen, Benutzer)
   - Screenshots gemäß obiger Liste erstellen

2. **Screenshots optimieren:**
   - PNG-Optimierung mit TinyPNG oder ähnlich
   - Konsistente Größen und Auflösungen
   - Annotationen hinzufügen (Pfeile, Beschriftungen) wo hilfreich

3. **README.md aktualisieren:**
   - Screenshots an passenden Stellen einfügen
   - Bildunterschriften hinzufügen
   - Verlinken zu den Originaldateien

4. **Guides aktualisieren:**
   - Für DGPTM Suite Guides-Bereich
   - Screenshots für Schritt-für-Schritt-Anleitungen

## Priorisierung

**Hohe Priorität (Must-have):**
- Umfragen-Übersicht
- Fragen verwalten
- Voting-Interface
- Beamer Live-Ergebnisse
- Zoom-Einstellungen
- Anwesenheitsliste

**Mittlere Priorität (Should-have):**
- Scanner-Interface
- Manuelle Suche
- Benutzer-Verwaltung
- Mobile Ansichten

**Niedrige Priorität (Nice-to-have):**
- Fehler-Screenshots
- Edge Cases
- Alle Diagramm-Varianten
