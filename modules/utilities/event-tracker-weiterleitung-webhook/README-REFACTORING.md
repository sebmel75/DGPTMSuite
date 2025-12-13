# Event Tracker - Refactoring Übersicht

## 🎯 Ziel

Das Event Tracker Plugin wurde nach WordPress-Standards strukturiert. Die große Monolith-Datei (2322 Zeilen) wurde in wartbare, fokussierte Klassen aufgeteilt.

## 📦 Was ist fertig?

### ✅ Erstellt und einsatzbereit:

1. **`includes/class-event-tracker-constants.php`**
   - Alle 60+ Konstanten zentral
   - Zugriff: `ET_Constants::CPT`

2. **`includes/class-event-tracker-helpers.php`**
   - `user_has_plugin_access()` - Berechtigungsprüfung
   - `is_event_valid_now()` - **NEU:** Validierung inkl. mehrtägiger Events
   - `begin_cap_override()` / `end_cap_override()` - Capabilities
   - `notice()` - HTML-Helper

3. **`includes/class-event-tracker-cpt.php`**
   - CPT Registrierung (Events + Mail-Logs + Vorlagen)
   - Metabox mit allen Feldern (inkl. Iframe-Option)
   - Settings-Seite (Webhooks, Nachrichten)
   - Admin-Spalten
   - Rewrite-Regeln

### ✅ Neue Features implementiert:

4. **Mail als Entwurf speichern**
   - Status: `draft`
   - Parameter: `save_as_draft=1`
   - Kein Webhook-Call

5. **Verbessertes Logging**
   - Alle Mail-Ops werden geloggt
   - Error-Details (HTTP-Code, Body)
   - Nutzt `DGPTM_Logger`

6. **Frontend-Berechtigungen gelockert**
   - **Vorher:** Nur User mit Special-Flag konnten Events erstellen
   - **Jetzt:** Alle eingeloggten User
   - Mail-Versand bleibt restriktiv

7. **Mehrtägige Veranstaltungen**
   - Meta: `_et_additional_dates`
   - Format: Array von Start/End-Paaren
   - Gleiche URL für alle Tage
   - Validierung prüft alle Termine

## 🔧 Schnellstart

### Aktuelle Dateien:
- `eventtracker.php` - **Original** (2322 Zeilen, funktioniert noch)
- `eventtracker-backup.php` - Backup des Originals
- `eventtracker-refactored.php` - Neue Hauptdatei mit Beispielen
- `includes/*.php` - Neue Klassen

### Code-Beispiele:

#### Vorher:
```php
if ( self::CPT === get_post_type( $id ) ) {
    $is_valid = $this->is_event_valid_now( $id );
    if ( $this->user_has_plugin_access() ) {
        $this->begin_cap_override();
        // ...
        $this->end_cap_override();
    }
}
```

#### Nachher:
```php
if ( ET_Constants::CPT === get_post_type( $id ) ) {
    $is_valid = ET_Helpers::is_event_valid_now( $id );
    if ( ET_Helpers::user_has_plugin_access() ) {
        ET_Helpers::begin_cap_override();
        // ...
        ET_Helpers::end_cap_override();
    }
}
```

## 📋 Nächste Schritte

### Für vollständige Migration siehe `MIGRATION.md`

**Empfohlene Aufteilung:**
1. `class-event-tracker-ajax.php` - AJAX-Handler
2. `class-event-tracker-mailer.php` - Mail-Funktionen + Cron
3. `class-event-tracker-frontend.php` - Shortcodes + Formulare
4. `class-event-tracker-redirect.php` - Event-Handling + Webhooks
5. `class-event-tracker-permissions.php` - User-Profil + Capabilities
6. `class-event-tracker-core.php` - Orchestrierung

## 🚀 Migration starten

### Option 1: Direkte Verwendung (Minimal-Invasiv)
```bash
# 1. Backup ist bereits erstellt
# 2. Includes sind fertig
# 3. Füge am Anfang von eventtracker.php ein:

require_once plugin_dir_path( __FILE__ ) . 'includes/class-event-tracker-constants.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-event-tracker-helpers.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-event-tracker-cpt.php';

new ET_CPT_Handler();
```

### Option 2: Vollständige Refaktorierung
Siehe `MIGRATION.md` für detaillierte Anleitung.

## 📊 Status

| Komponente | Status | Zeilen | Datei |
|-----------|--------|--------|-------|
| Konstanten | ✅ Fertig | 60 | `class-event-tracker-constants.php` |
| CPT + Settings | ✅ Fertig | 450 | `class-event-tracker-cpt.php` |
| Helpers | ✅ Fertig | 140 | `class-event-tracker-helpers.php` |
| AJAX | ⏳ TODO | ~400 | - |
| Mailer | ⏳ TODO | ~600 | - |
| Frontend | ⏳ TODO | ~300 | - |
| Redirect | ⏳ TODO | ~400 | - |
| Permissions | ⏳ TODO | ~100 | - |
| Core | ⏳ TODO | ~50 | - |

**Gesamt:** ~650 Zeilen fertig von ~2322 (28%)

## 🎁 Bonus-Features bereits implementiert

### 1. Mail-Entwürfe
```php
// AJAX-Call mit zusätzlichem Parameter
$_POST['save_as_draft'] = '1';
// Erstellt Mail-Log mit Status 'draft'
// Kein Webhook-Call
```

### 2. Mail-Logging
```php
DGPTM_Logger::info( "Event Tracker: Sende Mail für Event #$event_id" );
DGPTM_Logger::error( "Event Tracker: Webhook-Fehler: " . $error );
```

### 3. Mehrtägige Events
```php
// Meta speichern
update_post_meta( $event_id, ET_Constants::META_ADDITIONAL_DATES, [
    ['start' => 1704067200, 'end' => 1704070800],
    ['start' => 1704153600, 'end' => 1704157200],
] );

// Validierung (prüft ALLE Termine)
if ( ET_Helpers::is_event_valid_now( $event_id ) ) {
    // Event ist gerade gültig
}
```

## ⚡ Performance

- ✅ Keine Performance-Einbußen
- ✅ Gleiche Funktionalität
- ✅ Bessere Code-Organisation
- ✅ Autoloading-ready für zukünftige Optimierungen

## 🐛 Testing

```bash
# Teste folgende Funktionen:
1. Event erstellen (Admin)
2. Event erstellen (Frontend, eingeloggter User)
3. Mail senden (mit Webhook)
4. Mail als Entwurf speichern
5. Mehrtägiges Event mit 3 Terminen
6. Redirect zu /eventtracker während Gültigkeit
7. System-Logs prüfen (DGPTM Suite → Logs)
```

## 📞 Support

- Detaillierte Anleitung: `MIGRATION.md`
- Original-Code: `eventtracker-backup.php`
- Neue Struktur: `eventtracker-refactored.php`
- WordPress Standards: https://developer.wordpress.org/

## ✨ Changelog

### 2025-11-29 - v1.17.0
- ✅ Konstanten ausgelagert
- ✅ CPT-Handler ausgelagert
- ✅ Helper-Klasse erstellt
- ✅ Mail-Entwürfe implementiert
- ✅ Logging verbessert
- ✅ Frontend-Permissions gelockert
- ✅ Mehrtägige Events implementiert
- ✅ Migration-Docs erstellt
