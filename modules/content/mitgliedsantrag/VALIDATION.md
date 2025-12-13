# Validierungsregeln - DGPTM Mitgliedsantrag

## Übersicht der Validierungen

Dieses Dokument beschreibt alle Validierungsregeln, die im Mitgliedsantragsformular implementiert sind.

---

## 📧 E-Mail-Validierung

### Regel 1: Erste E-Mail-Adresse erforderlich
- **Feld:** `email1` (Private E-Mail-Adresse)
- **Regel:** MUSS ausgefüllt werden
- **Fehlermeldung:** "Die erste E-Mail-Adresse ist erforderlich"
- **Hinweis im Formular:** "Erforderlich."

### Regel 2: Keine doppelten E-Mail-Adressen
- **Felder:** `email1`, `email2`, `email3`
- **Regel:** Alle eingegebenen E-Mail-Adressen müssen unterschiedlich sein
- **Vergleich:** Case-insensitive (email@test.de = EMAIL@TEST.DE)
- **Fehlermeldung:** "Diese E-Mail-Adresse wurde bereits angegeben"

**Beispiel:**
```
✓ Gültig:
  Email 1: max@mustermann.de
  Email 2: max.mustermann@firma.de
  Email 3: (leer)

✗ Ungültig:
  Email 1: max@mustermann.de
  Email 2: max@mustermann.de  ← Duplikat!
  Email 3: (leer)
```

### Regel 3: E-Mail-Format-Validierung
- **Alle E-Mail-Felder:** Format muss gültig sein
- **Regex:** `/^[^\s@]+@[^\s@]+\.[^\s@]+$/`
- **Zusätzlich:** DNS MX Record Check (wenn Wert eingegeben)
- **Fehlermeldung:** "Ungültige E-Mail-Adresse" oder "E-Mail-Domain existiert nicht"

---

## 🏠 Adressvalidierung

### Regel 4: Pflichtfelder
- **Felder:** Straße, PLZ, Stadt
- **Regel:** Alle drei Felder MÜSSEN ausgefüllt sein
- **Fehlermeldung:** "Bitte füllen Sie alle Adressfelder aus"

### Regel 5: PLZ-Format (Deutschland)
- **Feld:** `plz`
- **Regel:** Genau 5 Ziffern für Deutschland
- **Regex:** `/^\d{5}$/`
- **Fehlermeldung:** "Ungültige Postleitzahl (Format: 12345)"

### Regel 6: Google Maps Geocoding API Validierung
**Voraussetzung:** Google Maps API Key muss in den Einstellungen konfiguriert sein

#### 6.1 Adresse muss existieren
- **Status:** ZERO_RESULTS
- **Fehlermeldung:** "Die angegebene Adresse konnte nicht gefunden werden. Bitte überprüfen Sie Ihre Eingabe."

#### 6.2 Präzision auf Straßenebene
- **Required Types:** `street_address`, `premise`, oder `subpremise`
- **Fehlermeldung:** "Bitte geben Sie eine vollständige Adresse mit Straße und Hausnummer an."

**Beispiel:**
```
✓ Gültig: Musterstraße 123, 12345 Musterstadt
✗ Ungültig: Musterstadt (keine Straße)
✗ Ungültig: Musterstraße (keine Hausnummer)
```

#### 6.3 PLZ-Verifizierung
- **Regel:** Die von Google gefundene PLZ muss mit der eingegebenen übereinstimmen
- **Fehlermeldung:** "Die Postleitzahl stimmt nicht überein. Gefunden: [gefundene PLZ]"

**Beispiel:**
```
Eingabe:
  Straße: Hauptstraße 1
  PLZ: 10115
  Stadt: Berlin

Google findet: Hauptstraße 1, 10117 Berlin
→ Fehler: PLZ stimmt nicht überein (10115 ≠ 10117)
```

#### 6.4 Erfolgreiche Validierung
- **Rückgabe:**
  - `formatted_address`: Von Google formatierte Adresse
  - `coordinates`: Latitude und Longitude
  - Wird als Tooltip beim Stadt-Feld angezeigt

---

## 👥 Bürgen-Validierung

### Regel 7: Beide Bürgen erforderlich
- **Felder:** `buerge1_input`, `buerge2_input`
- **Regel:** Beide Bürgen müssen eingegeben UND verifiziert sein
- **Fehlermeldung:** "Bürge [1/2] muss ein verifiziertes Mitglied sein."

### Regel 8: Bürgen müssen unterschiedliche Personen sein

#### 8.1 Vergleich via CRM Contact ID
- **Regel:** `buerge1_id` ≠ `buerge2_id`
- **Fehlermeldung:** "Bürge 2 muss eine andere Person als Bürge 1 sein."

#### 8.2 Vergleich via E-Mail-Adresse
- **Regel:** `buerge1_email` ≠ `buerge2_email` (case-insensitive)
- **Fehlermeldung:** "Bürge 2 muss eine andere Person als Bürge 1 sein (gleiche E-Mail-Adresse)."

**Beispiel:**
```
✓ Gültig:
  Bürge 1: Hans Müller (ID: 123456, hans.mueller@example.com)
  Bürge 2: Maria Schmidt (ID: 789012, maria.schmidt@example.com)

✗ Ungültig - Gleiche Person:
  Bürge 1: Hans Müller (ID: 123456, hans.mueller@example.com)
  Bürge 2: Dr. Hans Müller (ID: 123456, hans.mueller@example.com)
  → Fehler: Gleiche Contact ID

✗ Ungültig - Gleiche E-Mail:
  Bürge 1: hans.mueller@example.com
  Bürge 2: Hans.Mueller@example.com  ← Case-insensitive Match!
  → Fehler: Gleiche E-Mail-Adresse
```

### Regel 9: Bürgen müssen gültige Mitglieder sein
- **Verifizierung:** Via Zoho CRM API
- **Gültige Membership Types:**
  - Ordentliches Mitglied
  - Außerordentliches Mitglied
  - Korrespondierendes Mitglied
- **Status-Anzeige:**
  - ✓ Grün = Gültiges Mitglied gefunden
  - ✗ Rot = Nicht gefunden
  - ⚠ Gelb = Gefunden, aber kein gültiges Mitglied

---

## 🎓 Studenten-Validierung

### Regel 10: Studienbescheinigung erforderlich (wenn Student)
- **Trigger:** Checkbox "Ich bin Student/in" aktiviert
- **Pflichtfelder:**
  - Studienrichtung
  - Studienbescheinigung (Datei-Upload)
  - Gültig bis (Jahr)

### Regel 11: Datei-Upload Validierung
- **Erlaubte Formate:** JPG, JPEG, PNG, PDF
- **Maximale Größe:** 5 MB
- **Fehlermeldungen:**
  - "Nur JPG, PNG oder PDF Dateien erlaubt."
  - "Datei zu groß (max. 5 MB)."

### Regel 12: Gültigkeitsjahr
- **Feld:** `studienbescheinigung_gueltig_bis`
- **Regel:** Muss zwischen 2025 und 2030 liegen
- **Fehlermeldung:** "Bitte geben Sie ein gültiges Jahr an (2025-2030)."

---

## 🔐 DSGVO-Validierung

### Regel 13: DSGVO-Zustimmung erforderlich
- **Feld:** `dsgvo_akzeptiert`
- **Regel:** Checkbox MUSS aktiviert sein
- **Fehlermeldung:** "Sie müssen der Datenverarbeitung zustimmen."

---

## 🔧 Konfiguration

### Google Maps API Setup

1. **API Key erstellen:**
   - Gehen Sie zu: https://console.cloud.google.com/apis/credentials
   - Erstellen Sie ein neues Projekt oder wählen Sie ein bestehendes
   - Klicken Sie auf "Anmeldedaten erstellen" → "API-Schlüssel"

2. **Geocoding API aktivieren:**
   - Gehen Sie zu: https://console.cloud.google.com/apis/library
   - Suchen Sie nach "Geocoding API"
   - Klicken Sie auf "Aktivieren"

3. **API Key einschränken (empfohlen):**
   - API-Einschränkungen: Nur "Geocoding API" auswählen
   - Anwendungseinschränkungen: HTTP-Referrer (Websites)
   - Ihre Domain hinzufügen (z.B., `*.example.com/*`)

4. **In WordPress eintragen:**
   - WordPress Admin → Mitgliedsantrag
   - "Google Maps API Key" Feld ausfüllen
   - Einstellungen speichern

### Zoho CRM Setup

Siehe Hauptdokumentation (README.md) für OAuth-Konfiguration.

---

## 📊 Validierungsreihenfolge

### Step 2: Adresse
1. Pflichtfelder (Straße, PLZ, Stadt)
2. PLZ-Format (Deutschland)
3. E-Mail 1 Pflichtfeld
4. E-Mail-Format (alle Felder)
5. Keine doppelten E-Mails
6. **Real-time:** Google Maps Validierung (1,5 Sekunden nach letzter Eingabe)
7. **Real-time:** DNS MX Check für E-Mails (1 Sekunde nach letzter Eingabe)

### Step 3: Studienbescheinigung (conditional)
Nur wenn "Ich bin Student/in" aktiviert:
1. Studienrichtung ausgefüllt
2. Datei hochgeladen
3. Dateiformat erlaubt
4. Dateigröße ≤ 5 MB
5. Gültigkeitsjahr zwischen 2025-2030

### Step 4: Bürgen
1. Bürge 1 verifiziert (✓ grün)
2. Bürge 2 verifiziert (✓ grün)
3. Bürge 1 ≠ Bürge 2 (Contact ID)
4. Bürge 1 ≠ Bürge 2 (E-Mail)

### Step 5: Bestätigung
1. DSGVO Checkbox aktiviert

---

## 🐛 Debugging

### Google Maps API Fehler

**ZERO_RESULTS:**
- Adresse existiert nicht
- Tippfehler in Straßenname, Stadt oder PLZ
- **Lösung:** Eingabe überprüfen

**OVER_QUERY_LIMIT:**
- API-Quota überschritten
- **Lösung:** API-Quota in Google Cloud Console erhöhen oder später versuchen

**REQUEST_DENIED:**
- API Key ungültig oder nicht konfiguriert
- Geocoding API nicht aktiviert
- **Lösung:** API Key und Aktivierung prüfen

**INVALID_REQUEST:**
- Adresse-Parameter fehlt oder ungültig
- **Lösung:** Alle Adressfelder ausfüllen

### Debug-Logging

Aktivieren Sie WordPress Debug-Modus:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Relevante Log-Einträge:
```
[DGPTM Mitgliedsantrag] Google Maps API error: [Fehlermeldung]
[DGPTM Mitgliedsantrag] Google Maps API status: ZERO_RESULTS
[DGPTM Mitgliedsantrag] Google Maps API invalid response
```

---

## ✅ Checkliste für Deployment

- [ ] Google Maps API Key erstellt und konfiguriert
- [ ] Geocoding API in Google Cloud aktiviert
- [ ] API Key in WordPress eingetragen
- [ ] Zoho CRM OAuth konfiguriert (für Bürgen-Verifizierung)
- [ ] Testformular ausgefüllt mit echten Adressen
- [ ] Validierungen getestet:
  - [ ] Doppelte E-Mails werden blockiert
  - [ ] Ungültige Adressen werden abgelehnt
  - [ ] Gleiche Bürgen werden erkannt
  - [ ] Studienbescheinigung Pflicht für Studenten
- [ ] Debug-Logs überprüft (keine Fehler)

---

## 📞 Support

Bei Problemen mit der Validierung:
1. Prüfen Sie den WordPress Debug-Log (`wp-content/debug.log`)
2. Suchen Sie nach Einträgen mit `[DGPTM Mitgliedsantrag]`
3. Überprüfen Sie die Google Cloud Console für API-Fehler
4. Testen Sie die Adressvalidierung manuell in Google Maps
