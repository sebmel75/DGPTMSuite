# DGPTM Projektmanagement

Ein umfassendes Projektmanagement-Modul fuer die DGPTM Plugin Suite mit Projekt-Templates, Aufgabenverwaltung und Token-basiertem Zugang.

## Features

- **Projektverwaltung**: Erstellen, bearbeiten und verwalten von Projekten
- **Aufgabenverwaltung**: Aufgaben mit Prioritaeten, Faelligkeitsdaten und Datei-Anhaengen
- **Projekt-Templates**: Wiederverwendbare Vorlagen fuer schnelle Projekterstellung
- **Kommentarsystem**: Kommentare zu Aufgaben hinzufuegen
- **Token-basierter Zugang**: Direktlinks fuer Aufgabenzugriff ohne Login
- **E-Mail-Benachrichtigungen**: Taegliche Zusammenfassung offener Aufgaben

## Shortcodes

### [dgptm-projektmanagement]
Hauptverwaltung fuer Projektmanager. Zeigt alle Projekte und ermoeglicht CRUD-Operationen.

**Berechtigung:** Nur fuer Projektmanager (User-Meta `pm_is_projektmanager = 1`)

### [dgptm-meine-aufgaben]
Zeigt dem eingeloggten Benutzer seine zugewiesenen Aufgaben.

**Berechtigung:** Eingeloggte Benutzer

### [dgptm-projekt-templates]
Verwaltung von Projekt-Vorlagen im Frontend.

**Berechtigung:** Nur fuer Projektmanager

## Berechtigungssystem

### Projektmanager werden
Im WordPress-Benutzerprofil erscheint ein Checkbox-Feld "Projektmanager". Aktivieren Sie dieses fuer Benutzer, die Projekte verwalten sollen.

**Programmatisch:**
```php
update_user_meta($user_id, 'pm_is_projektmanager', '1');
```

## Token-System

Jede Aufgabe erhaelt bei Erstellung einen einzigartigen 32-Zeichen-Token. Dieser ermoeglicht:

- Ansicht der Aufgabendetails ohne Login
- Hinzufuegen von Kommentaren
- Markieren der Aufgabe als erledigt

**Token-URL-Format:**
```
https://ihre-website.de/?pm_token=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

Der Token bleibt gueltig, bis die Aufgabe abgeschlossen wird.

## E-Mail-Benachrichtigungen

### Taegliche Zusammenfassung
Jeden Morgen um 07:00 Uhr erhalten Benutzer mit offenen Aufgaben eine E-Mail mit:

- Liste aller offenen Aufgaben
- Prioritaet und Faelligkeitsdatum
- Direktlinks (Token-URLs) zu jeder Aufgabe

### Aufgabenzuweisung
Bei Zuweisung einer Aufgabe erhaelt der Benutzer sofort eine E-Mail mit:

- Aufgabendetails
- Projektinformationen
- Direktlink zur Aufgabe

## Datenstruktur

### Custom Post Types

| CPT | Beschreibung |
|-----|--------------|
| `dgptm_project` | Projekte |
| `dgptm_task` | Aufgaben (nutzt WordPress-Kommentare) |
| `dgptm_proj_template` | Projekt-Vorlagen |
| `dgptm_task_template` | Aufgaben-Vorlagen |

### Post Meta - Projekte

| Meta Key | Beschreibung |
|----------|--------------|
| `_pm_status` | Status: `active`, `completed`, `archived` |
| `_pm_due_date` | Faelligkeitsdatum |

### Post Meta - Aufgaben

| Meta Key | Beschreibung |
|----------|--------------|
| `_pm_project_id` | Zugehoeriges Projekt |
| `_pm_assignee` | Zugewiesener Benutzer (User ID) |
| `_pm_priority` | Prioritaet: `high`, `medium`, `low` |
| `_pm_due_date` | Faelligkeitsdatum |
| `_pm_status` | Status: `pending`, `in_progress`, `completed` |
| `_pm_access_token` | 32-Zeichen Zugangstoken |
| `_pm_token_valid` | Token-Gueltigkeit: `1` oder `0` |
| `_pm_attachments` | Array von Attachment-IDs |
| `_pm_completed_date` | Abschlussdatum |

## AJAX-Endpunkte

### Projekte
- `pm_create_project` - Projekt erstellen
- `pm_update_project` - Projekt bearbeiten
- `pm_delete_project` - Projekt loeschen
- `pm_get_project` - Projekt laden
- `pm_get_project_tasks` - Aufgaben eines Projekts

### Aufgaben
- `pm_create_task` - Aufgabe erstellen
- `pm_update_task` - Aufgabe bearbeiten
- `pm_delete_task` - Aufgabe loeschen
- `pm_complete_task` - Aufgabe abschliessen
- `pm_get_task_details` - Aufgabendetails laden
- `pm_add_comment` - Kommentar hinzufuegen
- `pm_upload_attachment` - Datei hochladen
- `pm_get_users` - Benutzer fuer Zuweisung

### Templates
- `pm_save_template` - Vorlage speichern
- `pm_delete_template` - Vorlage loeschen
- `pm_get_template` - Vorlage laden
- `pm_get_templates` - Alle Vorlagen
- `pm_create_from_template` - Projekt aus Vorlage

### Token-basiert (ohne Login)
- `pm_token_complete` - Aufgabe via Token abschliessen
- `pm_token_comment` - Kommentar via Token

## Installation

1. Modul im DGPTM Suite Dashboard aktivieren
2. Seiten fuer Shortcodes anlegen:
   - Projektverwaltung: `[dgptm-projektmanagement]`
   - Meine Aufgaben: `[dgptm-meine-aufgaben]`
   - Templates: `[dgptm-projekt-templates]`
3. Projektmanager-Berechtigung fuer relevante Benutzer aktivieren

## Cron-Jobs

Das Modul registriert einen taeglichen Cron-Job:
- **Hook:** `pm_daily_task_emails`
- **Zeit:** Taeglich um 07:00 Uhr
- **Funktion:** Versendet E-Mail-Zusammenfassungen

Bei Deaktivierung wird der Cron-Job automatisch entfernt.

## Dateien

```
projektmanagement/
├── module.json
├── dgptm-projektmanagement.php      # Hauptklasse
├── README.md
├── includes/
│   ├── class-pm-post-types.php      # CPT-Registrierung
│   ├── class-pm-token-manager.php   # Token-Verwaltung
│   ├── class-pm-permissions.php     # Berechtigungen
│   ├── class-pm-template-manager.php # Template-Verwaltung
│   ├── class-pm-email-handler.php   # E-Mail-Templates
│   └── class-pm-cron-handler.php    # Cron-Jobs
├── templates/
│   ├── frontend-main.php            # Projektverwaltung
│   ├── frontend-my-tasks.php        # Meine Aufgaben
│   ├── frontend-templates.php       # Template-Verwaltung
│   ├── task-detail.php              # Token-Ansicht
│   └── partials/
│       └── modals.php               # Modale Dialoge
└── assets/
    ├── css/projektmanagement.css
    └── js/projektmanagement.js
```

## Version

1.0.0 - Initiale Version
