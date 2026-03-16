# DGPTM Abstimmen-Addon v4.0.0 - Anleitung

## Uebersicht

Das Abstimmen-Addon ist ein umfassendes System fuer:
- **Live-Abstimmungen** bei Mitgliederversammlungen (Praesenz + Online)
- **Zoom-Integration** mit automatischer Registrierung
- **Anwesenheitserfassung** per QR-Scanner und Zoom-Webhook
- **Beamer-Anzeige** mit Echtzeit-Diagrammen

---

## Schnellstart: Mitgliederversammlung durchfuehren

### Vorbereitung (1-2 Wochen vorher)

1. **Zoom-Zugangsdaten einrichten**
   - WP Admin → Einstellungen → Online-Abstimmung → Tab "Zoom"
   - Account-ID, Client-ID und Client-Secret der Zoom S2S OAuth App eintragen
   - Meeting/Webinar-ID eingeben
   - "Registrieren bei Gruen" und "Canceln bei Rot" aktivieren

2. **Zeitfenster setzen**
   - Tab "Allgemein" → Start- und End-Zeitpunkt der Anmeldephase
   - Ausserhalb dieses Zeitfensters wird der Button nicht angezeigt

3. **Abstimmung erstellen**
   - Seite mit `[manage_poll]` aufrufen (oder im Dashboard-Tab)
   - "Neue Abstimmung" → Titel eingeben, optional Logo hochladen
   - Fragen mit Antwortoptionen hinzufuegen
   - Fragetyp: Einfachauswahl (max_votes=1) oder Mehrfachauswahl (max_votes>1)

### Anmeldephase (Tage vorher)

4. **Mitglieder melden sich an**
   - Auf der Seite mit `[online_abstimmen_button]`
   - Gruener Button = "Ich nehme online teil" → automatische Zoom-Registrierung
   - Roter Button = "Ich nehme doch nicht teil" → Zoom-Abmeldung
   - Mitglieder erhalten automatisch eine E-Mail mit Zoom-Link

5. **Teilnehmerliste pruefen**
   - `[online_abstimmen_liste]` zeigt alle angemeldeten Teilnehmer
   - "Abgleich"-Tab im Admin: Vergleich lokale Anmeldungen vs. Zoom-Registrierungen

### Am Veranstaltungstag

6. **Praesenz-Erfassung starten**
   - Seite mit `[dgptm_presence_scanner]` am Eingang
   - Badges/QR-Codes scannen
   - Manuelle Suche ueber Suchbutton moeglich (Zoho CRM Lookup)

7. **Beamer einrichten**
   - Seite mit `[beamer_view]` im Vollbildmodus oeffnen
   - Zeigt automatisch die aktive Frage mit Live-Ergebnissen
   - QR-Code fuer mobile Teilnahme (oben rechts)

8. **Abstimmung durchfuehren**
   - Im `[manage_poll]` die Frage aktivieren
   - Beamer zeigt Frage + Live-Balkendiagramm
   - Teilnehmer stimmen ueber `[member_vote]` oder QR-Code ab
   - Nach Abstimmungsende: Frage stoppen und Ergebnisse freigeben

9. **Ergebnisse praesentieren**
   - Beamer wechselt automatisch auf freigegebene Ergebnisse
   - Modus "Alle Ergebnisse" zeigt Gesamtuebersicht als Grid

### Nachbereitung

10. **Export**
    - CSV-Export der Stimmen
    - PDF-Export der Anwesenheitsliste
    - Beide im Manager-Dashboard verfuegbar

---

## Shortcodes

### Abstimmung & Verwaltung

| Shortcode | Beschreibung | Wer sieht es |
|-----------|-------------|--------------|
| `[manage_poll]` | Abstimmungs-Manager (Fragen erstellen, aktivieren, Ergebnisse) | Abstimmungsmanager / Admins |
| `[beamer_view]` | Vollbild-Beameranzeige mit Live-Diagrammen | Abstimmungsmanager |
| `[member_vote]` | Abstimmungsformular fuer Teilnehmer | Alle (mit Token/Link) |

### Zoom & Online-Teilnahme

| Shortcode | Beschreibung | Wer sieht es |
|-----------|-------------|--------------|
| `[online_abstimmen_button]` | An-/Abmelde-Button (Gruen/Rot) mit Zoom-Integration | Eingeloggte Benutzer |
| `[online_abstimmen_liste]` | Teilnehmerliste (Name, Status, Code) | Manager (optional MV-Flag) |
| `[online_abstimmen_code]` | Persoenlicher 6-stelliger Code | Eingeloggte Benutzer |
| `[online_abstimmen_zoom_link]` | Persoenlicher Zoom-Beitrittslink | Eingeloggte Benutzer |
| `[zoom_live_state]` | Zeigt ob Meeting gerade live ist | Alle |

### Anwesenheit

| Shortcode | Beschreibung | Wer sieht es |
|-----------|-------------|--------------|
| `[dgptm_presence_scanner]` | QR/Badge-Scanner mit Zoho-Suche | Manager |
| `[dgptm_presence_table]` | Live-Anwesenheitsliste (Online + Praesenz) | Manager |
| `[dgptm_registration_monitor]` | Echtzeit-Registrierungsmonitor (Kiosk) | Manager |

### Hilfs-Shortcodes

| Shortcode | Rueckgabe | Verwendung |
|-----------|-----------|-----------|
| `[abstimmungsmanager_toggle]` | `1` oder `0` | Berechtigungspruefung in Dashboard-Tabs |
| `[mitgliederversammlung_flag]` | `true` oder `false` | MV-Berechtigung pruefen |

---

## Beamer-Anzeigemodi

| Modus | Beschreibung |
|-------|-------------|
| **Auto** | Zeigt automatisch die aktuell aktive Frage mit Live-Ergebnissen |
| **Manuell** | Manager waehlt eine bestimmte Frage zur Anzeige |
| **Einzelergebnis** | Zeigt Ergebnis einer freigegebenen Frage |
| **Gesamtuebersicht** | Grid mit allen freigegebenen Ergebnissen |

Diagrammtypen: Balken (Standard), Kreis, Donut - pro Frage konfigurierbar.

---

## Rollen & Berechtigungen

| Rolle | Berechtigung |
|-------|-------------|
| **Administrator** | Voller Zugriff auf alle Funktionen |
| **Abstimmungsmanager** | User-Meta `toggle_abstimmungsmanager` = true |
| **MV-Berechtigter** | User-Meta `mitgliederversammlung` = true |
| **Teilnehmer** | Jeder eingeloggte Benutzer (fuer Abstimmung) |
| **Anonym** | Cookie-basierte Teilnahme (wenn erlaubt) |

---

## Zoom-Einrichtung

### Voraussetzungen
- Zoom-Konto mit Webinar-Lizenz (fuer Webinare)
- Server-to-Server OAuth App in marketplace.zoom.us
- Scopes: `meeting:read:admin`, `meeting:write:admin`, `webinar:read:admin`, `webinar:write:admin`

### Konfiguration
1. **Zoom-Zugangsdaten** im Admin eintragen (Account-ID, Client-ID, Client-Secret)
2. **Meeting/Webinar-ID** festlegen
3. **Webhook** (optional): URL `https://perfusiologie.de/wp-json/dgptm-zoom/v1/webhook` in der Zoom-App konfigurieren
4. **Webhook-Secret** im Admin eintragen (fuer Signaturpruefung)

### Automatische Funktionen
- Registrierung bei Klick auf gruenen Button
- Abmeldung bei Klick auf roten Button
- E-Mail mit Beitrittslink an Teilnehmer
- Anwesenheitserfassung ueber Webhook (Beitritt/Verlassen)

---

## Anwesenheitserfassung

### Quellen
1. **Zoom-Webhook**: Automatisch bei Beitritt/Verlassen des Meetings
2. **Praesenz-Scanner**: Manuelles Scannen am Eingang

### Scanner-Einrichtung
```
[dgptm_presence_scanner
  webhook="https://zoho-webhook-url"
  meeting_number="12345678901"
  kind="webinar"
  save_on="green,yellow"
  search_webhook="https://zoho-search-url"]
```

- **webhook**: URL fuer Badge-Lookup (Zoho Flow)
- **meeting_number**: Zoom Meeting-ID (fuer Zuordnung)
- **kind**: `auto`, `meeting` oder `webinar`
- **save_on**: Welche Ergebnisse gespeichert werden (`green` = Mitglied gefunden)
- **search_webhook**: URL fuer manuelle Namenssuche

### Export
- **PDF**: Anwesenheitsliste mit Zeitstempeln und Typ (Praesenz/Online)
- **CSV**: Tabellarischer Export aller Teilnehmer

---

## Datenbanktabellen

| Tabelle | Inhalt |
|---------|--------|
| `wp_dgptm_abstimmung_polls` | Abstimmungen (Name, Status, Logo) |
| `wp_dgptm_abstimmung_poll_questions` | Fragen (Text, Optionen, Diagrammtyp) |
| `wp_dgptm_abstimmung_participants` | Teilnehmer (User-ID, Token, Cookie) |
| `wp_dgptm_abstimmung_votes` | Abgegebene Stimmen (Frage, Option, User) |

---

## Fehlerbehebung

### Zoom-Registrierung schlaegt fehl
- Zoom-Zugangsdaten pruefen (Admin → Zoom-Tab)
- "Verbindung testen" Button nutzen
- Log aktivieren (Admin → Log-Tab) und Fehler pruefen
- Meeting-ID muss korrekt sein (nur Ziffern)

### Beamer zeigt keine Daten
- Ist eine Frage aktiviert? (manage_poll → Frage auf "Aktiv" setzen)
- Browser-Konsole auf JS-Fehler pruefen
- Beamer-Modus pruefen (Auto vs. Manuell)

### Scanner findet Mitglied nicht
- Badge-Format pruefen (Zoho CRM ID oder EFN?)
- Webhook-URL testen
- Manuelle Suche als Alternative nutzen

### Teilnehmer kann nicht abstimmen
- Ist die Frage aktiv? (Status = "active")
- Hat der Teilnehmer schon abgestimmt? (eine Stimme pro Frage)
- Cookie `DGPTMVOTE_voteid` vorhanden?

---

## Integration im Dashboard

Das Abstimmen-Addon kann im Mitglieder-Dashboard als Tab eingebunden werden:

**Tab-Inhalt (HTML):**
```
[member_vote]
```

**Berechtigung:**
- `always` fuer alle Mitglieder
- `sc:abstimmungsmanager_toggle` nur fuer Manager
- `acf:mitgliederversammlung` nur fuer MV-Berechtigte

**Manager-Dashboard:**
```
<p><a href="/mitgliedschaft/interner-bereich/abstimmungsmanager/">Abstimmungsmanager</a></p>
<p><a href="/mitgliedschaft/interner-bereich/abstimmungsmanager/abstimmungstool-beamer/" target="_blank">Beameranzeige (Vollbild)</a></p>
[member_vote]
```
