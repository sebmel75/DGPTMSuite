# Artikel-Einreichung — Dashboard-Integration & Reviewer/Autoren-Management

**Datum:** 2026-03-28
**Modul:** artikel-einreichung (content) + crm-abruf (core-infrastructure)
**Ziel:** Dashboard-Kompatibilitaet, Reviewer-Gruppen mit CRM-Anbindung, automatisches Rollen-Management fuer Reviewer und Autoren.

---

## 1. Dashboard-CSS (Forum-Vorbild)

### Scope

`.dgptm-dash` Overrides am Ende von `assets/css/frontend.css`. Betrifft alle 5 Frontend-Shortcodes:
- `[artikel_einreichung]` — Einreichungsformular
- `[artikel_dashboard]` — Autoren-Dashboard
- `[artikel_review]` — Reviewer-Dashboard
- `[artikel_redaktion]` — Redaktions-Dashboard
- `[artikel_editor_dashboard]` — Editor-in-Chief Dashboard

### Aenderungen im Dashboard-Kontext

| Element | Standalone | Dashboard |
|---------|-----------|-----------|
| Buttons | eigenes Design | `4px 10px`, `12px`, `#0073aa`, `4px` radius, `!important` |
| Inputs/Textareas | eigenes Design | `8px` padding, `1px solid #ccc`, `4px` radius |
| Tabellen | Shadow, abgerundet | Flach, `border-bottom: 1px solid #eee`, kein Shadow |
| Status-Badges | gross | `2px 8px`, `10px` radius, `0.75em` |
| Cards/Sections | Shadow, `12px` radius | Kein Shadow, `4px` radius |
| Modals | Overlay | Bleiben eigenstaendig |
| Form-Labels | - | `font-weight: 500`, `13px` |
| Abstaende | grosszuegig | kompakter (12-15px) |

Kein Template-Umbau — nur CSS-Overrides (~100-120 Zeilen).

### Token-Zugriff

Der bestehende Token-basierte Zugriff auf Artikel (fuer nicht-eingeloggte Autoren und Reviewer) bleibt vollstaendig erhalten. Die Dashboard-Integration ist ein **zusaetzlicher** Zugriffsweg — Token-URLs funktionieren weiterhin unabhaengig.

---

## 2. Reviewer-Gruppen-Management

### Pool-Datenstruktur

Option: `dgptm_artikel_reviewers`

**Aktuell:** Array von User-IDs `[12, 45, 78]`

**Neu:** Array von Reviewer-Objekten:

```json
[
  { "user_id": 12, "active": true, "zoho_id": "123456789", "added_at": "2026-03-28" },
  { "user_id": 45, "active": true, "zoho_id": "987654321", "added_at": "2026-03-28" },
  { "user_id": 78, "active": false, "zoho_id": "345678901", "added_at": "2026-01-15" }
]
```

### Migration

Beim ersten Laden: alte `[12, 45, 78]` Struktur automatisch in neue Struktur konvertieren. Alle bestehenden Reviewer bekommen `active: true`.

### WP-Rolle `reviewer`

| Aktion | Rolle |
|--------|-------|
| Reviewer zum Pool hinzufuegen (aktiv) | `reviewer` vergeben |
| Reviewer auf aktiv setzen | `reviewer` vergeben |
| Reviewer auf inaktiv setzen | `reviewer` entziehen |
| Reviewer aus Pool entfernen | `reviewer` entziehen |

### Reviewer hinzufuegen — erweiterter Ablauf

```
Editor gibt Name/E-Mail ein
  |
  +-- Parallele Suche:
  |     1. WP-Users (bestehend)
  |     2. Zoho CRM Contacts (NEU)
  |
  +-- Ergebnis anzeigen mit Quelle (WP / CRM / Beide)
  |
  +-- Editor waehlt Person aus:
        |
        +-- WP-User existiert?
        |     +-- JA: Rolle `reviewer` hinzufuegen, zoho_id verknuepfen
        |     +-- NEIN: WP-User anlegen + `reviewer` + Einladungs-E-Mail
        |
        +-- CRM-Contact existiert?
              +-- JA: zoho_id in WP-User-Meta speichern
              +-- NEIN: Neuen Contact im CRM anlegen, zoho_id verknuepfen
```

### Nicht-Mitglieder als Reviewer

Wenn eine Person weder in WP noch im CRM gefunden wird:
1. WP-User anlegen (E-Mail = Username, generiertes Passwort)
2. Rolle `reviewer` zuweisen
3. `wp_new_user_notification()` sendet Einladungs-E-Mail mit Passwort-Reset-Link
4. Zoho CRM Contact anlegen mit First_Name, Last_Name, Email + Tag "Reviewer"
5. `zoho_id` in WP-User-Meta speichern

---

## 3. Zoho CRM Integration

### Neue Datei: `includes/class-crm-reviewer.php`

Kapselt alle CRM-Operationen fuer Reviewer:

```php
class DGPTM_CRM_Reviewer {
    public function search_contact_by_email($email)  // GET /crm/v7/Contacts/search?email=...
    public function search_contact_by_name($name)     // GET /crm/v7/Contacts/search?criteria=...
    public function create_contact($data)              // POST /crm/v7/Contacts
    public function link_user($user_id, $zoho_id)      // update_user_meta(zoho_id)
}
```

### API-Aufrufe

| Methode | Endpoint | Felder |
|---------|----------|--------|
| Suche (E-Mail) | `GET /crm/v7/Contacts/search?email={email}` | - |
| Suche (Name) | `GET /crm/v7/Contacts/search?criteria=(Full_Name:equals:{name})` | - |
| Anlegen | `POST /crm/v7/Contacts` | First_Name, Last_Name, Email, Tag: "Reviewer" |

### Abhaengigkeit

Nutzt `DGPTM_Zoho_Plugin::get_instance()->get_oauth_token()` und `dgptm_safe_remote()` aus dem `crm-abruf` Modul. Keine eigene OAuth-Logik. Falls `crm-abruf` nicht aktiv: CRM-Suche wird uebersprungen, nur WP-User-Suche.

---

## 4. Autoren-Rollen-Management

### Rolle `zeitschrift_autor`

Wird automatisch verwaltet basierend auf aktiven Einreichungen.

| Ausloeser | Aktion |
|-----------|--------|
| Neue Einreichung erstellt | `zeitschrift_autor` Rolle vergeben |
| Alle Artikel des Users: Status abgelehnt oder veroeffentlicht | Rolle entziehen |

### Einreichungsformular — nicht eingeloggte User

```
User oeffnet [artikel_einreichung]
  |
  +-- Eingeloggt?
  |     +-- JA: Formular wie bisher, Rolle bei Submit
  |
  +-- NEIN: Login-Hinweis + Formular sichtbar
        |
        +-- User reicht ein (hauptautor_email aus Formular):
              |
              +-- WP-User mit E-Mail vorhanden?
              |     +-- JA: Artikel zuweisen, Rolle hinzufuegen
              |
              +-- NEIN: WP-User anlegen
                    +-- Username = E-Mail-Prefix
                    +-- Rolle: zeitschrift_autor
                    +-- Willkommens-E-Mail mit Login-Link
                    +-- Zoho CRM: Contact suchen/anlegen, zoho_id verknuepfen
```

### Rollen-Cleanup

Bei jedem Login (`wp_login` Hook) pruefen:
- Hat der User Rolle `zeitschrift_autor`?
- Hat er noch aktive Einreichungen? (Status nicht in: abgelehnt, veroeffentlicht)
- Falls nein: Rolle entziehen

---

## 5. AJAX-Handler Aenderungen

### Bestehende Handler aendern

| Handler | Aenderung |
|---------|-----------|
| `ajax_search_users` | Erweitern: zusaetzlich Zoho CRM durchsuchen, Ergebnisse mit Quelle markieren |
| `ajax_add_reviewer` | Erweitern: Pool-Struktur (active, zoho_id), Rolle vergeben, CRM-Link |
| `ajax_remove_reviewer` | Erweitern: Rolle entziehen, active=false setzen |
| `ajax_assign_reviewer` | Zusaetzlich: Rolle pruefen/vergeben |
| `ajax_submit_artikel` | Erweitern: User-Lookup/Anlage bei nicht-eingeloggten, Rolle vergeben |
| `ajax_save_reviewer_list` | Migration auf neue Datenstruktur |

### Neue Handler

| Handler | Zweck |
|---------|-------|
| `ajax_toggle_reviewer_active` | Reviewer aktiv/inaktiv schalten + Rolle |
| `ajax_search_crm_contacts` | Zoho CRM Contacts durchsuchen |
| `ajax_create_reviewer_user` | WP-User + CRM-Contact + Rolle anlegen |

---

## 6. Dateien die geaendert/erstellt werden

| Datei | Aenderung |
|-------|-----------|
| `artikel-einreichung.php` | AJAX-Handler erweitern, Rollen-Logik, Login-Hinweis, Pool-Migration |
| `includes/class-crm-reviewer.php` | **NEU** — Zoho CRM Contact-Suche/Anlage |
| `assets/css/frontend.css` | +100-120 Zeilen `.dgptm-dash` Overrides |
| `assets/js/admin.js` | Reviewer-Suche erweitern (CRM-Ergebnisse), aktiv/inaktiv Toggle |
| `assets/js/frontend.js` | Login-Hinweis bei nicht-eingeloggten Usern |
| `templates/admin/reviewers.php` | Aktiv/Inaktiv-Status anzeigen, CRM-Badge |
| `templates/submission-form.php` | Login-Hinweis-Block |
