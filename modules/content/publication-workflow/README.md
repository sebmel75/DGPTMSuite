# Publication Frontend Manager (Enhanced) - Dokumentation

Version: 3.0.0
Autor: Sebastian Melzer

## Übersicht

Das **Publication Frontend Manager** Plugin ist ein professionelles, vollständiges Publikations-Management-System für WordPress mit erweiterten Features für wissenschaftliche Journals und Publikationen.

## 🎯 Hauptfunktionen

### 1. **Erweitertes Dashboard mit AJAX-Filterung & AI-Quellenprüfung (NEU in v3.0.0)**
- ✅ Statistik-Karten: Übersicht aller Publikationen gruppiert nach Status
- ✅ AJAX-basierte Filterung ohne Seitenreload
- ✅ 3 Ansichtsmodi: Karten-Grid, Listen-Ansicht, Tabellen-Ansicht
- ✅ Live-Suche nach Titel/Autor
- ✅ Sortierung nach Datum/Titel (aufsteigend/absteigend)
- ✅ Pagination für große Datenmengen
- ✅ **AI-gestützte Literaturverifikation**:
  - Automatische Erkennung von DOI und PubMed IDs
  - Überprüfung der DOI-Auflösung (HTTP-Status-Check)
  - Automatische Verlinkung zu DOI, PubMed, Google Scholar
  - Farbcodierung: Grün (✓ valide), Rot (✗ fehlerhaft), Gelb (? unklar)
  - Nur für Redaktion/Editor in Chief sichtbar
- ✅ Rollenbasierte Filterung (Autoren sehen nur eigene, Reviewer nur zugewiesene)

### 2. **Erweiterte Review-Kriterien & Scoring-System**
- ✅ Strukturierte Bewertung anhand von 6 Kriterien
- ✅ Gewichtete Gesamtbewertung (1-5 Sterne)
- ✅ Aggregierte Scores über mehrere Reviews
- ✅ Visuelle Darstellung mit Fortschrittsbalken

**Kriterien:**
- Methodik & Forschungsdesign (25%)
- Relevanz & Originalität (20%)
- Klarheit & Struktur (15%)
- Literatur & Referenzen (15%)
- Ergebnisse & Diskussion (15%)
- Darstellung & Sprache (10%)

### 2. **Visueller Workflow-Tracker**
- ✅ Timeline-Ansicht des Publikationsprozesses
- ✅ Status-Indikatoren mit Icons und Farben
- ✅ Automatische Status-Historie
- ✅ Fortschrittsbalken-Visualisierung

**Workflow-Stages:**
- Eingereicht (submitted)
- Im Review (under_review)
- Nachbesserung (revision_needed)
- Akzeptiert (accepted)
- Abgelehnt (rejected)
- Veröffentlicht (published)

### 3. **E-Mail-Template-System**
- ✅ 10 vordefinierte E-Mail-Vorlagen
- ✅ Anpassbare Templates im Backend
- ✅ Platzhalter-System für dynamische Inhalte
- ✅ Automatische Benachrichtigungen

**Templates:**
- Einreichungsbestätigung
- Reviewer-Zuweisung
- Review-Erinnerung
- Entscheidungsbenachrichtigungen (Accept/Revision/Reject)
- Veröffentlichungsbestätigung

### 4. **Editorial Decision Interface**
- ✅ Zusammenfassende Review-Übersicht
- ✅ Aggregierte Bewertungen aller Reviewer
- ✅ Entscheidungsvorschläge basierend auf Reviews
- ✅ Schnellentscheidungs-Buttons
- ✅ Entscheidungshistorie

### 5. **Erweiterte Dateiverwaltung**
- ✅ Vollständige Versionskontrolle
- ✅ Versionsvergleich
- ✅ Versionstypes (Initial, Revision, Final, Proofread)
- ✅ Supplementary Materials Management
- ✅ Datei-Statistiken

### 6. **Conflict of Interest (COI) System**
- ✅ COI-Deklarationen für Reviewer
- ✅ Reviewer-Ausschlusslisten für Autoren
- ✅ Automatische Eignungsprüfung
- ✅ COI-Status-Übersicht für Redaktion

### 7. **Analytics & Reporting**
- ✅ Submission-Statistiken
- ✅ Review-Performance-Metriken
- ✅ Akzeptanz-/Ablehnungsraten
- ✅ Time-to-Decision Analysen
- ✅ Reviewer-Aktivitäts-Reports
- ✅ CSV-Export

### 8. **Automatische Erinnerungen**
- ✅ Review-Deadline-Reminders
- ✅ Überfälligkeits-Benachrichtigungen
- ✅ Entscheidungs-Pending-Alerts
- ✅ Revisions-Deadline-Reminders
- ✅ Cron-basiertes System

### 9. **Moderne UI/UX**
- ✅ Responsive Design
- ✅ Drag & Drop File Upload
- ✅ Live-Validierung
- ✅ Tooltips & Hilfestellungen
- ✅ Animationen & Transitions
- ✅ Druckfreundliches Layout

## 📋 Shortcodes

### Haupt-Shortcodes

```
[publikation_view_enhanced id="123"]
```
Erweiterte Einzelansicht mit allen neuen Features (Workflow-Tracker, Reviews, Editorial Decision, etc.)

```
[publikation_submit]
```
Einreichungsformular für Autoren

```
[publikation_dashboard]
```
**Erweitertes Dashboard (NEU in v3.0.0)** mit:
- AJAX-basierte Filterung nach Status (Alle, Eingereicht, Im Review, Nachbesserung, Akzeptiert, Abgelehnt, Veröffentlicht)
- Statistik-Karten mit Übersicht aller Publikationen nach Status
- 3 Ansichtsmodi: Karten (Grid), Liste, Tabelle
- Live-Suche und Sortierung
- Pagination
- AI-gestützte Quellenprüfung (DOI, PubMed, Google Scholar)
- Rollenbasierte Zugriffssteuerung (Autor/Redakteur/Reviewer)

```
[publikation_dashboard_simple]
```
Legacy-Dashboard mit einfacher Tabellenliste (alte Version vor v3.0.0)

```
[publikation_analytics]
```
Analytics Dashboard (nur für Redaktion)

```
[publikation_deadlines]
```
Widget mit bevorstehenden Deadlines

```
[publikation_list_frontend]
```
Liste aller Publikationen

## 👥 Benutzerrollen

### Editor in Chief
- Volle Kontrolle über alle Funktionen
- Reviewer-Zuweisung
- Editorial Decisions
- DOI-Vergabe
- Crossref-Deposits
- Analytics-Zugriff

### Redaktion
- Review-Management
- Editorial Decisions
- Reviewer-Zuweisung
- Analytics-Zugriff

### Reviewer
- COI-Deklarationen
- Review-Einreichung mit Kriterien
- Zugriff auf zugewiesene Publikationen

### Autor
- Einreichung von Manuskripten
- Upload von Revisionen
- Status-Tracking
- Reviewer-Ausschlussliste

## 🎨 Design-System

### Farben
- Primary: #3498db (Blau)
- Success: #27ae60 (Grün)
- Warning: #f39c12 (Orange)
- Danger: #e74c3c (Rot)
- Info: #667eea (Lila)

### Status-Farben
- Submitted: #0073aa
- Under Review: #f0b849
- Revision Needed: #d54e21
- Accepted: #46b450
- Rejected: #dc3232
- Published: #00a32a

## 📊 Datenbankstruktur

### Post Meta (publikation)
```
pfm_abstract              - Abstract
pfm_keywords              - Keywords
pfm_volume                - Volume
pfm_issue                 - Issue
pfm_pub_year              - Publikationsjahr
pfm_first_page            - Erste Seite
pfm_last_page             - Letzte Seite
pfm_article_number        - Artikelnummer
pfm_license_url           - Lizenz-URL
pfm_manuscript_attachment_id - Aktuelles Manuskript
pfm_supplementary_ids     - Zusätzliche Dateien
pfm_file_versions         - Versionsverlauf
pfm_current_version       - Aktuelle Version
pfm_assigned_reviewers    - Zugewiesene Reviewer
pfm_review_deadline       - Review-Deadline
pfm_revision_deadline     - Revisions-Deadline
pfm_status_history        - Status-Historie
pfm_editorial_decisions   - Entscheidungshistorie
pfm_reviewer_coi          - COI-Deklarationen
pfm_reviewer_exclusions   - Ausschlussliste
pfm_reminder_log          - Erinnerungs-Log
```

### Comment Meta (Reviews)
```
pfm_recommendation        - Empfehlung (accept/minor/major/reject)
pfm_comments_to_author    - Kommentare für Autor
pfm_confidential_to_editor - Vertrauliche Kommentare
pfm_review_scores         - Bewertungs-Scores
pfm_review_weighted_score - Gewichteter Gesamtscore
pfm_review_attachment_id  - Angehängte Datei
```

### User Meta
```
pfm_is_editor_in_chief    - Editor in Chief Flag
pfm_is_redaktion          - Redaktions Flag
pfm_is_reviewer           - Reviewer Flag
pfm_coi_declarations      - COI-Deklarationen
```

## 🔧 Installation

1. Kopieren Sie den gesamten Ordner nach `wp-content/plugins/dgptm-plugin-suite/modules/content/publication-workflow/`

2. Stellen Sie sicher, dass alle Ordner vorhanden sind:
   ```
   /includes/
   /assets/css/
   /assets/js/
   ```

3. Aktivieren Sie das Modul im DGPTM Suite Dashboard

4. Konfigurieren Sie die Einstellungen unter **Einstellungen → Publication Frontend Manager**

## ⚙️ Konfiguration

### Crossref-Einstellungen
1. DOI-Prefix eintragen
2. Crossref-Zugangsdaten eingeben
3. Journal-Informationen (ISSN, Titel, Publisher)
4. Lizenz-Standard-URL festlegen

### E-Mail-Templates anpassen
Navigieren Sie zu **Einstellungen → Email Templates** um die Vorlagen anzupassen.

### Cron-Jobs
Das System nutzt WordPress Cron für automatische Erinnerungen. Stellen Sie sicher, dass WP-Cron aktiviert ist.

## 📧 E-Mail-Benachrichtigungen

### Automatische Benachrichtigungen
- ✅ Bei neuer Einreichung (an Redaktion)
- ✅ Bei Reviewer-Zuweisung
- ✅ 3 Tage vor Review-Deadline
- ✅ 1 Tag vor Review-Deadline
- ✅ Bei überfälliger Deadline
- ✅ Bei eingegangenen Reviews
- ✅ Bei Editorial Decision
- ✅ Bei Veröffentlichung

## 🔐 Sicherheit

- ✅ Nonce-Verifizierung für alle Formulare
- ✅ Capability-Checks für alle Aktionen
- ✅ Sanitization aller Eingaben
- ✅ Escaping aller Ausgaben
- ✅ AJAX mit Nonce-Schutz

## 📱 Responsive Design

Das System ist vollständig responsive und funktioniert auf:
- ✅ Desktop (>1200px)
- ✅ Tablet (768px - 1199px)
- ✅ Mobile (< 768px)

## 🚀 Performance

### Optimierungen
- CSS und JS werden nur bei Bedarf geladen
- Minimale Datenbankabfragen
- Caching-freundliche Struktur
- Effiziente Meta-Query-Nutzung

## 🐛 Debugging

Aktivieren Sie WordPress Debug-Modus:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Logs finden Sie unter `wp-content/debug.log`

## 📝 Workflow-Beispiel

### Typischer Ablauf einer Publikation:

1. **Autor** reicht Manuskript ein → Status: `submitted`
2. **Redaktion** prüft und weist Reviewer zu
3. Status wechselt zu `under_review`
4. **Reviewer** erhalten E-Mail und geben COI-Deklaration ab
5. **Reviewer** bewerten anhand Kriterien und reichen Reviews ein
6. **Redaktion** erhält Benachrichtigung über eingegangene Reviews
7. **Redaktion** sieht aggregierte Scores und Entscheidungsvorschlag
8. **Redaktion** trifft Editorial Decision:
   - **Accept** → Status: `accepted` → DOI-Vergabe → Veröffentlichung
   - **Revision** → Status: `revision_needed` → Autor lädt neue Version hoch
   - **Reject** → Status: `rejected` → Prozess beendet
9. Bei Revision: Neue Review-Runde oder direkte Entscheidung
10. **Veröffentlichung** → Status: `published` → Crossref-Deposit

## 🔄 Updates

### Von Version 2.0 auf 3.0
Die neue Version ist vollständig kompatibel mit vorhandenen Daten. Alle bestehenden Meta-Daten bleiben erhalten.

**Neue Datenbankfelder werden automatisch hinzugefügt:**
- Keine manuelle Migration erforderlich
- Alte Reviews bleiben sichtbar
- Neue Reviews nutzen erweiterte Kriterien

## 💡 Tipps & Best Practices

### Für Redakteure
1. Weisen Sie mindestens 2 Reviewer pro Publikation zu
2. Setzen Sie realistische Review-Deadlines (14-21 Tage)
3. Nutzen Sie die Entscheidungsvorschläge als Orientierung
4. Dokumentieren Sie Editorial Decisions ausführlich

### Für Reviewer
1. Geben Sie COI-Deklaration zeitnah ab
2. Nutzen Sie alle Bewertungskriterien
3. Geben Sie konstruktives, detailliertes Feedback
4. Halten Sie Deadlines ein

### Für Autoren
1. Reichen Sie vollständige Manuskripte ein
2. Benennen Sie potenzielle Interessenkonflikte
3. Laden Sie Revisionen zeitnah hoch
4. Dokumentieren Sie Änderungen in Revisionen

### Für Redakteure: Nutzung der Quellenprüfung
1. Öffnen Sie das Dashboard mit `[publikation_dashboard]`
2. Scrollen Sie zum Abschnitt "Literaturquellen-Verifikation"
3. Wählen Sie eine Publikation aus der Dropdown-Liste
4. Klicken Sie auf "Quellen prüfen"
5. Das System analysiert automatisch alle Literaturangaben:
   - **Grün (✓)**: DOI/PubMed ID gefunden und validiert
   - **Rot (✗)**: DOI nicht auflösbar oder fehlerhaft
   - **Gelb (?)**: Keine Identifikatoren gefunden, nur Google Scholar-Suche verfügbar
6. Klicken Sie auf die Links (DOI, PubMed, Google Scholar) um Quellen zu überprüfen
7. Informieren Sie Autoren über fehlerhafte Quellenangaben

## 🔌 API & AJAX-Endpunkte (NEU in v3.0.0)

### AJAX-Handler für Dashboard

**`pfm_load_dashboard_publications`**
- **Zweck**: Lädt und filtert Publikationen für das Dashboard
- **Parameter**:
  - `filter`: Status-Filter (all, submitted, under_review, revision_needed, accepted, rejected, published)
  - `search`: Suchbegriff (Titel/Autor)
  - `sort`: Sortierung (date_desc, date_asc, title_asc, title_desc)
  - `page`: Seitennummer für Pagination
- **Rückgabe**: JSON mit publications-Array, Gesamt-Anzahl, Seitenanzahl
- **Sicherheit**: Nonce-Verifizierung, rollenbasierte Zugriffssteuerung

**`pfm_verify_literature`**
- **Zweck**: Verifiziert Literaturangaben einer Publikation
- **Parameter**:
  - `post_id`: ID der zu prüfenden Publikation
- **Funktionen**:
  - Extrahiert DOI-Pattern (10.xxxx/xxxxx)
  - Extrahiert PubMed IDs (PMID: xxxxxx)
  - Prüft DOI-Auflösung via wp_remote_head()
  - Generiert Google Scholar-Suchlinks
  - Kategorisiert Quellen als valid/invalid/uncertain
- **Rückgabe**: HTML mit farbcodierten Verifikationsergebnissen
- **Sicherheit**: Nur für Editor in Chief und Redaktion

### JavaScript-API (Dashboard-Object)

```javascript
// Publikationen laden
Dashboard.loadPublications();

// Filter ändern
Dashboard.changeFilter('published');

// Ansicht wechseln
Dashboard.changeView('grid'); // oder 'list', 'table'

// Literatur prüfen
Dashboard.verifyLiterature(postId);
```

## 🆘 Support

Bei Fragen oder Problemen:
1. Prüfen Sie die debug.log
2. Kontaktieren Sie den Administrator
3. Dokumentieren Sie Fehlermeldungen mit Screenshots

## 📜 Lizenz

Dieses Plugin ist proprietär und für die Nutzung innerhalb des DGPTM Suite Systems lizenziert.

## 🎓 Credits

Entwickelt von Sebastian Melzer für professionelle wissenschaftliche Publikations-Workflows.

---

**Version:** 3.0.0
**Letzte Aktualisierung:** 2024
**Kompatibilität:** WordPress 5.8+, PHP 7.4+
