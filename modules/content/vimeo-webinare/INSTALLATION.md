# Installation & Quick Start Guide

## ⚡ Schnellstart

### 1. Modul aktivieren
1. WordPress Admin → **DGPTM Suite → Dashboard**
2. Finden Sie "DGPTM - Vimeo Webinare" in der Kategorie "Media"
3. Klicken Sie auf **Aktivieren**
4. Modul wird geladen und ACF-Felder werden registriert

### 2. Erstes Webinar erstellen
1. WordPress Admin → **Webinare → Neu hinzufügen**
2. Titel: z.B. "Einführung in die Telemedizin"
3. Beschreibung: Kurze Zusammenfassung des Inhalts
4. **Webinar Einstellungen** ausfüllen:
   ```
   Vimeo Video ID: 123456789
   Erforderlicher Fortschritt: 90
   EBCP Punkte: 2.5
   VNR: (optional)
   ```
5. Klicken Sie auf **Veröffentlichen**

### 3. Webinar-Seite erstellen
1. WordPress Admin → **Seiten → Neu hinzufügen**
2. Titel: "Webinar: Einführung in die Telemedizin"
3. Inhalt:
   ```
   [vimeo_webinar id="123"]
   ```
   (Ersetzen Sie `123` mit der tatsächlichen Post-ID)
4. Veröffentlichen

### 4. Webinar-Liste erstellen
1. WordPress Admin → **Seiten → Neu hinzufügen**
2. Titel: "Verfügbare Webinare"
3. Inhalt:
   ```
   [vimeo_webinar_liste]
   ```
4. Veröffentlichen

### 5. Frontend-Manager einrichten
1. WordPress Admin → **Seiten → Neu hinzufügen**
2. Titel: "Webinar Manager"
3. Inhalt:
   ```
   [vimeo_webinar_manager]
   ```
4. Veröffentlichen

### 6. Manager-Berechtigung vergeben
1. WordPress Admin → **Benutzer**
2. Wählen Sie einen Benutzer → **Bearbeiten**
3. Scrollen Sie zu **Webinar Manager Berechtigung**
4. Aktivieren Sie "Webinar Manager"
5. Speichern

## 📋 Vollständige Installationsschritte

### Voraussetzungen prüfen
```
✅ WordPress 5.8 oder höher
✅ PHP 7.4 oder höher
✅ Advanced Custom Fields Plugin installiert und aktiviert
✅ DGPTM Plugin Suite installiert und aktiviert
```

### Installation

#### Schritt 1: Modul aktivieren
Das Modul ist bereits Teil der DGPTM Plugin Suite.

1. Gehen Sie zu **DGPTM Suite → Dashboard**
2. Suchen Sie nach "Vimeo Webinare" in der Kategorie "Media"
3. Klicken Sie auf den **Toggle-Button** zum Aktivieren
4. Warten Sie auf die Bestätigung

#### Schritt 2: Vimeo-Video vorbereiten
1. Laden Sie Ihr Video auf Vimeo hoch
2. Notieren Sie die Video-ID (aus der URL):
   ```
   https://vimeo.com/123456789
                      ^^^^^^^^^ Dies ist die ID
   ```
3. Stellen Sie sicher, dass Embedding aktiviert ist:
   - Vimeo → Video → Settings → Privacy → "Who can embed this video" → "Anyone"

#### Schritt 3: Erstes Webinar erstellen

**Via WordPress Backend:**
1. **Webinare → Neu hinzufügen**
2. Füllen Sie folgende Felder aus:

   **Basis-Informationen:**
   - Titel: z.B. "Kardiovaskuläre Diagnostik 2025"
   - Beschreibung: Ausführliche Beschreibung des Webinarinhalts
   - Beitragsbild: Thumbnail für das Webinar (optional)

   **Webinar Einstellungen:**
   - **Vimeo Video ID:** `123456789` (nur die Zahlen!)
   - **Erforderlicher Fortschritt:** `90` (%)
   - **Fortbildungspunkte:** `2.5` (EBCP)
   - **VNR:** `123456` (optional)
   - **Art der Fortbildung:** `Webinar`
   - **Ort:** `Online`

   **Zertifikat-Anpassung (optional):**
   - **Hintergrundbild:** Laden Sie ein A4-Hintergrundbild hoch (297x210mm, Querformat)
   - **Wasserzeichen:** Laden Sie ein Logo als Wasserzeichen hoch (PNG mit Transparenz)

3. Klicken Sie auf **Veröffentlichen**
4. Notieren Sie die Post-ID (in der URL nach `post=`)

#### Schritt 4: Seiten erstellen

**A) Einzelnes Webinar anzeigen:**

1. **Seiten → Neu hinzufügen**
2. Titel: "Webinar: [Webinar-Name]"
3. Permalink anpassen: z.B. `/webinar-kardiovaskulaere-diagnostik`
4. Shortcode einfügen:
   ```
   [vimeo_webinar id="123"]
   ```
   (Ersetzen Sie `123` mit der Post-ID aus Schritt 3)
5. Veröffentlichen

**B) Webinar-Übersicht:**

1. **Seiten → Neu hinzufügen**
2. Titel: "Alle Webinare"
3. Permalink: z.B. `/webinare`
4. Shortcode einfügen:
   ```
   [vimeo_webinar_liste]
   ```
5. Optional: Einleitungstext hinzufügen
6. Veröffentlichen

**C) Manager-Bereich:**

1. **Seiten → Neu hinzufügen**
2. Titel: "Webinar Management"
3. Permalink: z.B. `/webinar-verwaltung`
4. Shortcode einfügen:
   ```
   [vimeo_webinar_manager]
   ```
5. **Wichtig:** Seiten-Sichtbarkeit auf "Privat" setzen oder mit einem Membership-Plugin schützen
6. Veröffentlichen

#### Schritt 5: Menü-Navigation einrichten

1. **Design → Menüs**
2. Erstellen Sie ein neues Menü oder bearbeiten Sie ein bestehendes
3. Fügen Sie die erstellten Seiten hinzu:
   - "Alle Webinare" (für alle Benutzer)
   - "Webinar Management" (nur für Manager/Admins sichtbar)
4. Speichern

#### Schritt 6: Berechtigungen konfigurieren

**Manager-Berechtigung vergeben:**

1. **Benutzer → Alle Benutzer**
2. Wählen Sie einen Benutzer aus
3. Klicken Sie auf **Bearbeiten**
4. Scrollen Sie zum Abschnitt **Webinar Manager Berechtigung**
5. Aktivieren Sie die Checkbox "Webinar Manager"
6. Klicken Sie auf **Benutzer aktualisieren**

**Hinweis:** Administratoren haben automatisch Zugriff auf den Manager, auch ohne diese Berechtigung.

## 🧪 Test-Szenario

### Test 1: Webinar als Teilnehmer durchlaufen

1. **Logout** aus dem Admin-Account
2. **Login** als normaler Benutzer
3. Navigieren Sie zur "Alle Webinare"-Seite
4. Klicken Sie auf ein Webinar
5. **Erwartetes Verhalten:**
   - Vimeo Player lädt
   - Fortschrittsbalken zeigt 0%
   - Info-Box erklärt die Anforderungen
6. Starten Sie das Video
7. Springen Sie zu 90% des Videos
8. **Erwartetes Verhalten:**
   - Fortschrittsbalken aktualisiert sich
   - Bei 90% erscheint grüne Benachrichtigung
   - Seite lädt neu
   - "Webinar abgeschlossen"-Banner erscheint
   - Button "Zertifikat herunterladen" verfügbar
9. Klicken Sie auf "Zertifikat herunterladen"
10. **Erwartetes Verhalten:**
    - PDF wird generiert und geöffnet
    - Enthält Name, Webinar-Titel, Punkte, Datum

### Test 2: Manager-Funktionen testen

1. **Login** als Benutzer mit Manager-Berechtigung
2. Navigieren Sie zur "Webinar Management"-Seite
3. **Erwartetes Verhalten:**
   - Liste aller Webinare wird angezeigt
   - Buttons: Bearbeiten, Statistik, Löschen
4. Klicken Sie auf **"Neues Webinar erstellen"**
5. Füllen Sie das Formular aus
6. Klicken Sie auf **Speichern**
7. **Erwartetes Verhalten:**
   - Erfolgs-Benachrichtigung
   - Seite lädt neu
   - Neues Webinar erscheint in der Liste
8. Klicken Sie auf **Statistik-Icon** eines Webinars
9. **Erwartetes Verhalten:**
   - Modal öffnet sich
   - Zeigt Abgeschlossen, In Bearbeitung, Gesamt

### Test 3: Fortbildungseintrag prüfen

1. **Login** als Admin
2. Navigieren Sie zu **Fortbildungen**
3. **Erwartetes Verhalten:**
   - Fortbildungseintrag für abgeschlossenes Webinar vorhanden
   - Titel entspricht Webinar-Titel
   - Benutzer ist korrekt zugeordnet
   - EBCP-Punkte sind eingetragen
   - Datum ist aktuelles Datum
   - "Freigegeben" ist auf "Ja"
   - "Freigabe durch" ist "System (Webinar)"

## ⚙️ Erweiterte Konfiguration

### Zertifikat-Template anpassen

Wenn Sie das Zertifikat-Layout anpassen möchten:

1. Öffnen Sie: `dgptm-vimeo-webinare.php`
2. Finden Sie die Funktion: `generate_certificate_pdf()`
3. Passen Sie FPDF-Befehle an:
   ```php
   $pdf->SetFont('Arial', 'B', 24);
   $pdf->SetY(40);
   $pdf->Cell(0, 10, $this->pdf_text('Ihre Überschrift'), 0, 1, 'C');
   ```

### Standard-Werte ändern

Um Standard-Werte zu ändern, bearbeiten Sie die ACF-Feldgruppe-Registrierung:

```php
// In register_acf_fields()
[
    'key' => 'field_vw_completion_percentage',
    'default_value' => 95, // Ändern Sie hier den Standardwert
],
```

### Statistik-Berechnung erweitern

Um zusätzliche Metriken zu erfassen:

```php
// In get_webinar_stats()
// Fügen Sie weitere Datenbankabfragen hinzu
```

## 🔧 Troubleshooting

### Problem: "Vimeo Player API nicht geladen"

**Lösung:**
1. Prüfen Sie Browser-Konsole (F12)
2. Stellen Sie sicher, dass externe Scripts erlaubt sind
3. Testen Sie: `https://player.vimeo.com/api/player.js` manuell aufrufen

### Problem: "ACF-Felder werden nicht angezeigt"

**Lösung:**
1. Prüfen Sie, ob ACF aktiviert ist
2. Deaktivieren und reaktivieren Sie das Modul
3. Leeren Sie den WordPress Cache
4. Prüfen Sie: `Webinare → Neu hinzufügen` → Sollte "Webinar Einstellungen" zeigen

### Problem: "Fortbildungseintrag wird nicht erstellt"

**Lösung:**
1. Prüfen Sie, ob Fortbildung Post Type existiert: `WordPress Admin → Fortbildungen`
2. Aktivieren Sie WordPress Debug: `WP_DEBUG = true` in `wp-config.php`
3. Prüfen Sie Debug-Log: `wp-content/debug.log`
4. Suchen Sie nach: `vw_complete_webinar`

### Problem: "Manager kann keine Webinare erstellen"

**Lösung:**
1. Prüfen Sie User Meta:
   ```php
   $manager = get_field('vw_is_manager', 'user_' . $user_id);
   var_dump($manager); // Sollte true sein
   ```
2. Leeren Sie Browser-Cache
3. Logout/Login
4. Prüfen Sie, ob ACF User-Felder aktiv sind

## 📞 Support

Bei weiteren Fragen oder Problemen:

1. **Dokumentation:** Lesen Sie README.md
2. **Debug-Log:** Aktivieren Sie WP_DEBUG und prüfen Sie Logs
3. **Browser-Konsole:** Öffnen Sie F12 und suchen Sie nach JavaScript-Fehlern
4. **DGPTM Support:** Kontaktieren Sie den technischen Support

---

**Viel Erfolg mit Ihrem Webinar-System!** 🎉
