# Microsoft 365 Groups - Anleitung

## Ueberblick

Das Modul **Microsoft 365 Groups** integriert die Microsoft Graph API in WordPress und ermoeglicht die Verwaltung von Microsoft 365-Gruppen direkt im Frontend. Benutzer koennen Gruppenmitglieder anzeigen, hinzufuegen und entfernen. Administratoren koennen zusaetzlich Anzeigenamen und Positionen (jobTitle) aendern sowie Benutzer loeschen. Die Kommunikation mit Microsoft ist vollstaendig verschluesselt (AES-256-GCM) und wird fuer Diagnosezwecke protokolliert.

## Voraussetzungen

- Keine DGPTM-Modul-Abhaengigkeiten
- **Azure AD App Registration** mit folgenden Berechtigungen (Application oder Delegated):
  - `User.Read`
  - `Group.ReadWrite.All`
  - `User.Invite.All`
  - `Directory.ReadWrite.All`
- Ein Microsoft 365-Tenant
- PHP 7.4+ mit OpenSSL-Erweiterung (fuer AES-256-GCM Verschluesselung)
- WordPress 5.8+

## Installation & Aktivierung

1. Modul im DGPTM Suite Dashboard aktivieren.
2. Azure AD App Registration anlegen (siehe unten).
3. Unter **Einstellungen > MS365 Plugin** die Zugangsdaten konfigurieren.
4. OAuth-Verbindung herstellen.

## Konfiguration

### Azure AD App Registration

1. Im Azure Portal (https://portal.azure.com) unter **Azure Active Directory > App registrations** eine neue App anlegen.
2. Einen Namen vergeben (z.B. "DGPTM WordPress MS365 Groups").
3. Als **Redirect URI** die Admin-Seite des Plugins eintragen:
   `https://ihre-domain.de/wp-admin/options-general.php?page=wp_ms365_plugin`
4. Unter **API permissions** folgende Delegated Permissions hinzufuegen:
   - `openid`
   - `profile`
   - `offline_access`
   - `User.Read`
   - `Group.ReadWrite.All`
   - `User.Invite.All`
   - `Directory.ReadWrite.All`
5. Admin-Einwilligung erteilen ("Grant admin consent").
6. Unter **Certificates & secrets** ein neues Client Secret erstellen und notieren.
7. Auf der **Overview**-Seite die **Application (client) ID** notieren.

### Plugin-Einstellungen

Unter **Einstellungen > MS365 Plugin** folgende Werte eintragen:

| Feld | Beschreibung |
|---|---|
| **Client ID** | Application (client) ID aus der Azure App Registration |
| **Client Secret** | Das erstellte Client Secret (wird nur einmal eingegeben, danach verschluesselt gespeichert) |
| **Redirect URI** | Muss exakt mit der in Azure AD eingetragenen URI uebereinstimmen |
| **Security-Gruppen einbeziehen** | Checkbox: wenn aktiv, werden neben M365-Gruppen (Unified) auch Security-Gruppen angezeigt |

**Hinweis:** Der Tenant-ID ist fest im Plugin hinterlegt. Bei Aenderung muss der Quellcode angepasst werden.

### OAuth-Verbindung herstellen

1. Nach dem Speichern der Einstellungen auf **"Mit Microsoft verbinden"** klicken.
2. Bei Microsoft anmelden und die angeforderten Berechtigungen genehmigen.
3. Nach erfolgreicher Verbindung erscheint der Status "Verbunden" mit Token-Ablaufzeit.

Die Verbindung nutzt **PKCE** (Proof Key for Code Exchange) und **State-Parameter** fuer zusaetzliche Sicherheit. Token werden automatisch per Refresh Token erneuert.

### Benutzer-Gruppenzuordnung

Welche Microsoft-365-Gruppen ein WordPress-Benutzer im Frontend verwalten kann, wird im Benutzerprofil festgelegt:

1. Unter **Benutzer > Profil bearbeiten** im Bereich "Erlaubte MS365-Gruppen".
2. Die gewuenschten Gruppen ankreuzen.
3. Profil speichern.

Nur Gruppen, die dem Benutzer zugewiesen sind, erscheinen im Frontend-Shortcode.

## Benutzung

### Frontend: Gruppen verwalten

Der Shortcode `[ms365_group_manager]` zeigt eine interaktive Gruppenverwaltung:

**Bei einer zugewiesenen Gruppe:**
- Der Gruppenname wird direkt angezeigt.
- Darunter erscheint die Mitgliederliste.

**Bei mehreren zugewiesenen Gruppen:**
- Ein Dropdown zur Gruppenauswahl wird angezeigt.
- Nach Auswahl werden die Mitglieder der Gruppe geladen.

**Mitgliederliste zeigt pro Eintrag:**
- Anzeigename
- E-Mail-Adresse
- Position (jobTitle)

**Aktionen fuer alle berechtigten Benutzer:**
- Mitglied zur Gruppe hinzufuegen (E-Mail-Adresse eingeben)

**Zusaetzliche Aktionen fuer Administratoren (`manage_options`):**
- Anzeigenamen aendern
- Position (jobTitle) aendern
- Benutzer aus der Gruppe entfernen
- Benutzer loeschen

### Shortcode: Gruppenprüfung

Der Shortcode `[ms365_has_any_group]` gibt `1` zurueck, wenn der eingeloggte Benutzer mindestens eine MS365-Gruppe zugewiesen hat, sonst `0`. Nuetzlich fuer bedingte Inhalte:

```
[ms365_has_any_group]
```

### Shortcodes (Gesamtuebersicht)

| Shortcode | Parameter | Beschreibung |
|---|---|---|
| `[ms365_group_manager]` | keine | Interaktive Gruppenverwaltung im Frontend |
| `[ms365_has_any_group]` | keine | Gibt `1` oder `0` zurueck (hat der Benutzer zugewiesene Gruppen?) |

### Diagnose (Admin)

Auf der Einstellungsseite befindet sich ein Diagnose-Bereich:

- **Letzter Fehler:** Zeigt den letzten aufgetretenen Graph-API-Fehler.
- **Letzte Kommunikation:** Request-/Response-Details (geschwärzt).
- **"Manuell: Gruppen abrufen":** Testbutton, der alle verfuegbaren Gruppen vom Microsoft Graph abruft und die Kommunikation protokolliert.

## Gruppenfilter

Folgende Filter werden automatisch angewendet:

- **Nur Microsoft 365-Gruppen** ("Unified") werden standardmaessig angezeigt.
- **Security-Gruppen** koennen optional einbezogen werden (Checkbox in Einstellungen).
- **Gruppen mit "ADMIN"-Praefix** im Namen werden immer ausgeblendet.
- **Gruppen-Cache:** Erfolgreiche Abrufe werden 1 Stunde gecacht (Transient), leere Ergebnisse 5 Minuten.

## Fehlerbehebung

| Problem | Loesung |
|---|---|
| "Kein Access Token vorhanden" | Auf "Mit Microsoft verbinden" klicken und OAuth-Flow durchfuehren |
| "Keine Gruppen zugewiesen" | Im Benutzerprofil die erlaubten MS365-Gruppen zuweisen |
| Keine Gruppen gefunden | Diagnose-Bereich pruefen; ggf. Security-Gruppen-Checkbox aktivieren; Berechtigungen in Azure AD pruefen |
| "Ungültiger OAuth-Status (state)" | Erneut auf "Mit Microsoft verbinden" klicken; Redirect URI in Azure AD pruefen |
| Token abgelaufen / Refresh fehlgeschlagen | Erneut verbinden; Client Secret in Azure AD ggf. erneuern |
| Mitglied hinzufuegen schlaegt fehl | E-Mail-Adresse muss im Azure AD existieren; Berechtigungen pruefen |
| Position aendern nicht moeglich | Nur Administratoren (`manage_options`) koennen Positionen aendern |
| Anzeigename/Loeschen nicht moeglich | Nur Administratoren (`manage_options`) haben diese Rechte |
| Verschluesselungsfehler | PHP OpenSSL-Erweiterung muss aktiv sein; bei persistenten Problemen Token neu verbinden |

## Technische Details

- **Klasse:** `WP_MS365_Group_Manager` (Singleton)
- **Option:** `wp_ms365_plugin_options` (enthaelt verschluesselte Tokens, Client-ID, Redirect-URI, Security-Gruppen-Flag)
- **Verschluesselung:** AES-256-GCM mit HMAC-Integritaetspruefung; eigener persistenter Schluessel in `wp_ms365_enc_key`
- **Legacy-Migration:** Alte Tokens (Plain-JWT oder AES-256-CBC mit wp_salt) werden automatisch auf das neue GCM-Format migriert
- **Graph API Endpoint:** `https://graph.microsoft.com/v1.0/`
- **Tenant ID:** Fest im Code hinterlegt
- **Token Endpoint:** `https://login.microsoftonline.com/{tenant_id}/oauth2/v2.0/token`
- **Auth Endpoint:** `https://login.microsoftonline.com/{tenant_id}/oauth2/v2.0/authorize`
- **Transient-Cache:** `wp_ms365_tenant_groups` (1 Stunde bei Erfolg, 5 Minuten bei leerem Ergebnis)
- **User-Meta:** `ms365_allowed_groups` (Array von Gruppen-IDs), `ms365_allowed_groups_names` (Array mit Gruppennamen als Fallback)
- **AJAX-Actions:**
  - `wp_ms365_add_member` -- Mitglied hinzufuegen
  - `wp_ms365_remove_member` -- Mitglied entfernen
  - `wp_ms365_get_members` -- Mitglieder einer Gruppe laden
  - `wp_ms365_rename_user` -- Anzeigename aendern (nur Admin)
  - `wp_ms365_delete_user` -- Benutzer loeschen (nur Admin)
  - `wp_ms365_set_position` -- Position aendern (nur Admin)
  - `wp_ms365_admin_fetch_groups` -- Diagnose: Gruppen manuell abrufen
- **Admin-Seite:** Einstellungen > MS365 Plugin
- **Diagnose-Options:** `wp_ms365_last_graph_error`, `wp_ms365_last_graph_trace`
