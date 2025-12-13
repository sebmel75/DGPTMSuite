# Changelog

## Version 1.3.0 (2025-11-29)

### 🐛 Bugfixes
- **Zertifikate/Punkte-Vergabe**: Behoben - Progress wird jetzt konsistent in `_vw_progress_{id}` und `_vw_watched_time_{id}` gespeichert
- **Wasserzeichen im Zertifikat**: Das ACF-Feld `certificate_watermark` wird jetzt korrekt in der PDF-Generierung verwendet
- **Completion-Trigger**: Serverseitige Validierung des tatsächlichen Fortschritts vor Punktevergabe

### ✨ Neue Features

#### Eigene Datenbank-Tabellen
- **Automatische Erstellung bei erster Nutzung** (nicht bei Aktivierung - für Suite-Integration)
- `wp_vw_progress` - Speichert Benutzer-Fortschritt (schneller als User-Meta)
- `wp_vw_sessions` - Detailliertes Session-Tracking
- `wp_vw_certificates` - Zertifikat-Historie
- Fallback auf User-Meta wenn Tabellen nicht existieren
- Versions-Tracking für zukünftige Migrationen

#### Cookie-basierter Fortschritt für nicht-eingeloggte Benutzer
- Fortschritt wird per Cookie 30 Tage lang gespeichert
- Nach Login wird der Cookie-Fortschritt automatisch in die Datenbank übertragen
- Lokaler Fortschritt wird in der UI als "📱 Lokal" gekennzeichnet

#### Verlaufsanzeige
- "Zuletzt angesehen" Sektion in der Webinar-Liste für eingeloggte Benutzer
- Zeigt die letzten 5 angesehenen Webinare mit Fortschritt
- Relative Zeitangaben (Heute, Gestern, vor X Tagen)

#### Verbesserter Login-Hinweis
- Deutlicher Warnhinweis für nicht-eingeloggte Benutzer
- Information, dass Teilnahme nicht in Fortbildungsliste eingetragen wird
- Hinweis auf automatische Fortschritts-Übernahme nach Login

#### Zertifikat-Designer (Admin)
- Neuer visueller Editor für Zertifikat-Layout
- Live-Vorschau des Zertifikats
- PDF-Vorschau-Generierung
- Wasserzeichen-Einstellungen:
  - Bild-Upload
  - Position (5 Optionen: Mittig, Ecken)
  - Transparenz (10-100%)
- Pro-Webinar Wasserzeichen überschreibbar

#### Erfolgs-Modal
- Schönes Modal nach erfolgreichem Webinar-Abschluss
- Zeigt erhaltene EBCP-Punkte
- Direkter Download-Button für Zertifikat

### 🔧 Verbesserungen
- Fortschritts-Schwellwert-Anzeige im Fortschrittsbalken (rote Linie)
- Bessere visuelle Unterscheidung von lokalem vs. gespeichertem Fortschritt
- Responsive Design-Verbesserungen
- Performance-Optimierungen beim Tracking

### 📝 Technische Änderungen
- Neue Datenbank-Tabellen mit automatischer Erstellung bei erster Nutzung
- DB-Version-Tracking (`vw_db_version` Option)
- Neue AJAX-Handler: `vw_track_progress_nopriv`, `vw_transfer_cookie_progress`, `vw_preview_certificate`
- Neue Helper-Methoden: `save_user_progress()`, `mark_webinar_completed()`, `save_certificate()`, `table_exists()`
- Verbesserte FPDF-Integration mit Fallback-Pfaden
- Neue Admin-Seite: Zertifikat Designer
- Automatisches Erstellen des Zertifikate-Verzeichnisses mit .htaccess

### 🗄️ Datenbank-Schema

```sql
-- wp_vw_progress
CREATE TABLE wp_vw_progress (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    user_id bigint(20) unsigned NOT NULL,
    webinar_id bigint(20) unsigned NOT NULL,
    watched_time float NOT NULL DEFAULT 0,
    progress float NOT NULL DEFAULT 0,
    completed tinyint(1) NOT NULL DEFAULT 0,
    completed_date datetime DEFAULT NULL,
    fortbildung_id bigint(20) unsigned DEFAULT NULL,
    last_access datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY user_webinar (user_id, webinar_id)
);

-- wp_vw_sessions
CREATE TABLE wp_vw_sessions (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    user_id bigint(20) unsigned DEFAULT NULL,
    webinar_id bigint(20) unsigned NOT NULL,
    session_token varchar(64) DEFAULT NULL,
    watched_time float NOT NULL DEFAULT 0,
    ip_address varchar(45) DEFAULT NULL,
    user_agent varchar(255) DEFAULT NULL,
    started_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at datetime DEFAULT NULL,
    PRIMARY KEY (id)
);

-- wp_vw_certificates
CREATE TABLE wp_vw_certificates (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    user_id bigint(20) unsigned NOT NULL,
    webinar_id bigint(20) unsigned NOT NULL,
    fortbildung_id bigint(20) unsigned DEFAULT NULL,
    certificate_url varchar(500) NOT NULL,
    certificate_hash varchar(64) DEFAULT NULL,
    points float NOT NULL DEFAULT 0,
    generated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
);
```

## Version 1.2.4

- Siehe vorherige Changelog-Einträge
