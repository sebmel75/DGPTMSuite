# CRM-Abruf (Zoho CRM & API Endpoints) - Anleitung

## Ueberblick

Das Modul **CRM-Abruf** ist das zentrale Bindeglied zwischen der WordPress-Website und dem Zoho CRM. Es stellt ueber OAuth2 eine sichere Verbindung zu Zoho her, laedt Benutzerdaten aus dem CRM und macht sie per Shortcodes, JavaScript-Objekt (`window.zohoData`) und REST-API-Endpunkten verfuegbar. Zusaetzlich synchronisiert es automatisch die WordPress-Rolle "Mitglied" basierend auf dem Zoho-Feld `aktives_mitglied`.

**Kritisches Modul** -- kann nicht deaktiviert werden. Viele andere Module (Abstimmen-Addon, Event-Tracker, Fortbildung u.a.) haengen von CRM-Abruf ab.

## Voraussetzungen

- Keine weiteren DGPTM-Module erforderlich (keine Abhaengigkeiten)
- Ein Zoho CRM-Konto mit konfigurierter API-Funktion
- Zoho OAuth2-Zugangsdaten: Client ID, Client Secret, API-URL
- PHP 7.4+, WordPress 5.8+

## Installation & Aktivierung

1. Das Modul ist als **kritisch** markiert und in der Regel bereits aktiv.
2. Unter **Einstellungen > Zoho API** die Konfiguration vornehmen (Tab "Einstellungen").
3. OAuth2-Verbindung herstellen (siehe unten).

## Konfiguration

### Zoho OAuth2 einrichten

1. Im Zoho API Console (https://api-console.zoho.eu/) eine Server-based Application anlegen.
2. **Client ID** und **Client Secret** notieren.
3. Als Redirect URI die WordPress-Admin-URL eintragen:
   `https://ihre-domain.de/wp-admin/options-general.php?page=dgptm-zoho-api-settings`
4. In WordPress unter **Einstellungen > Zoho API**:
   - **API-URL** eintragen (die HTTPS-URL der Zoho API-Funktion, die Benutzerdaten liefert)
   - **Zoho Client ID** eintragen
   - **Zoho Client Secret** eintragen
   - Speichern
5. Auf **"Mit Zoho verbinden"** klicken und die OAuth2-Autorisierung bei Zoho durchfuehren.
6. Bei Erfolg erscheint "OAuth2 Verbindung ist hergestellt."

Das Access Token wird automatisch per Refresh Token erneuert (Gueltigkeitsdauer ca. 1 Stunde).

### Zoho-ID am Benutzerprofil

Jeder WordPress-Benutzer benoetigt eine **Zoho-ID** in seinem Profil, damit CRM-Daten abgerufen werden koennen:

1. Unter **Benutzer > Profil bearbeiten** das Feld "Zoho-ID" ausfuellen.
2. Die Zoho-ID muss eindeutig sein (keine Doppelvergabe moeglich).
3. Die Zoho-ID entspricht typischerweise der Record-ID im Zoho CRM (Modul "Contacts").

### Debug-Logging

1. In `wp-config.php` aktivieren:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```
2. Unter **Einstellungen > Zoho API** die Checkbox "Debug-Logging aktivieren" setzen.
3. Logs erscheinen in `wp-content/debug.log` mit dem Praefix `[DGPTM]`.

### Rollensynchronisation

Die Rolle **"mitglied"** wird automatisch beim ersten Zugriff auf Zoho-Daten nach dem Login synchronisiert:

- Zoho-Feld `aktives_mitglied` = `true` / `1` / `ja` --> Rolle "mitglied" wird hinzugefuegt
- Zoho-Feld `aktives_mitglied` = `false` / `0` / `nein` --> Rolle "mitglied" wird entfernt
- Verliert ein Benutzer alle Rollen, wird automatisch "subscriber" als Fallback zugewiesen
- Andere Benutzerrollen bleiben unberuehrt

Das Rollenwechsel-Protokoll ist einsehbar unter **Benutzer > Rollenwechsel-Log**.

### Zusaetzliche Endpunkte

Unter **Einstellungen > Zoho API > "Zusaetzliche Endpunkte"** koennen REST-API-Weiterleitungen konfiguriert werden:

| Feld | Beschreibung |
|---|---|
| **Slug** | Pfad unter `/wp-json/dgptm/v1/{slug}` (z.B. `crm/webhook`) |
| **Ziel-URL** | Muss HTTPS und oeffentlich erreichbar sein (SSRF-Schutz aktiv) |
| **Nur interne Aufrufe** | Wenn aktiv, sind nur Aufrufe aus WordPress/PHP erlaubt (HMAC-signiert) |
| **Weiterleitungs-Methode** | GET oder POST fuer den Forward |
| **Zoho-Auth mitsenden** | Fuegt `Authorization: Zoho-oauthtoken` Header hinzu |
| **Verknuepfte WP-Seite** | Optionaler 302-Redirect auf eine WordPress-Seite |

## Shortcodes

### [zoho_api_data]

Zeigt ein einzelnes Feld aus den Zoho-CRM-Daten des eingeloggten Benutzers an.

| Parameter | Beschreibung |
|---|---|
| `field="Feldname"` | Name des Zoho-Feldes (z.B. `Vorname`, `Nachname`, `Email`) |
| `module="Modulname"` | Optional: Direkter Zugriff auf ein Zoho CRM-Modul (z.B. `Contacts`) |

**Beispiel:**
```
Willkommen, [zoho_api_data field="Vorname"]!
```

### [zoho_api_data_ajax]

Wie `[zoho_api_data]`, aber clientseitig ueber `window.zohoData` gerendert (schneller bei vielen Feldern auf einer Seite).

| Parameter | Beschreibung |
|---|---|
| `field="Feldname"` | Name des Zoho-Feldes |
| `module="Modulname"` | Optional: Bei Angabe wird serverseitig gerendert (Fallback) |

**Beispiel:**
```
Mitgliedsstatus: [zoho_api_data_ajax field="Status"]
```

### [zoho_profile_card]

Zeigt mehrere Zoho-Felder als formatierte Profilkarte an.

| Parameter | Beschreibung |
|---|---|
| `fields="Feld1,Feld2,..."` | Kommaseparierte Feldnamen |
| `labels="Label1,Label2,..."` | Optionale Labels (Standard: Feldnamen) |
| `layout="list\|inline\|table"` | Darstellungsart (Standard: `list`) |
| `class="css-klasse"` | Optionale CSS-Klasse (Standard: `dgptm-profile-card`) |

**Beispiel:**
```
[zoho_profile_card fields="Vorname,Nachname,Mitgliedsart,Status,EFN" layout="table"]
```

### [ifcrmfield]

Bedingte Anzeige von Inhalten basierend auf Zoho-Feldern. Unterstuetzt `elseif` und `else`.

| Parameter | Beschreibung |
|---|---|
| `field="Feldname"` | Zoho-Feld fuer die Bedingung |
| `value="Wert"` | Erwarteter Wert (Vergleich case-insensitive) |
| `module="Modulname"` | Optional: CRM-Modul fuer direkten Zugriff |

**Beispiel:**
```
[ifcrmfield field="Mitgliedsart" value="Vollmitglied"]
  <p>Sie sind Vollmitglied.</p>
[elseif field="Mitgliedsart" value="Studentisch"]
  <p>Sie haben eine studentische Mitgliedschaft.</p>
[else]
  <p>Ihr Mitgliedsstatus ist unbekannt.</p>
[/ifcrmfield]
```

### [api-abfrage]

Fuehrt einen internen REST-Aufruf auf `/dgptm/v1/{slug}` aus und gibt ein Feld aus der Antwort zurueck. Setzt interne Signatur-Header automatisch.

| Parameter | Beschreibung |
|---|---|
| `slug="endpunkt"` | Slug des konfigurierten Endpunkts |
| `field="Feldname"` | Feld aus der JSON-Antwort |

**Beispiel:**
```
[api-abfrage slug="zusage" field="status"]
```

### [api-abruf]

AJAX-basierter Abruf der Ziel-URL eines konfigurierten Endpunkts.

### [zoho_api_antwort] / [dgptm_api_antwort]

Server-/AJAX-seitige Abrufe der Ziel-URL (Forwarder-Konfiguration wird genutzt).

## JavaScript-Objekt: window.zohoData

Fuer eingeloggte Benutzer wird im Footer automatisch ein JavaScript-Objekt bereitgestellt:

```javascript
// Im Browser verfuegbar:
window.zohoData = {
  "Vorname": "Max",
  "Nachname": "Mustermann",
  "Email": "max@example.de",
  "aktives_mitglied": "true",
  // ... weitere Felder aus dem Zoho CRM
};
```

Dieses Objekt kann von anderen Modulen und Frontend-Scripts genutzt werden.

## Interne Aufrufe (fuer Entwickler)

Endpunkte mit "Nur interne Aufrufe" koennen programmatisch aufgerufen werden:

```php
$slug   = "zusage";
$route  = "/dgptm/v1/" . $slug;
$req    = new WP_REST_Request("GET", $route);

// Query-Parameter:
$req->set_param("ref", "111");

// Interne HMAC-Header setzen:
$hdrs = dgptm_internal_signature_headers($slug, "GET");
$req->set_header("x-dgptm-ts",       $hdrs["x-dgptm-ts"]);
$req->set_header("x-dgptm-internal", $hdrs["x-dgptm-internal"]);

// Request ausfuehren:
$resp = rest_do_request($req);
```

## Fehlerbehebung

| Problem | Loesung |
|---|---|
| "Fehlende OAuth2-Konfiguration" | Client ID, Client Secret und API-URL unter Einstellungen > Zoho API pruefen |
| "Kein Zugriffstoken gefunden" | Erneut auf "Mit Zoho verbinden" klicken und OAuth-Flow durchfuehren |
| Benutzer sieht keine CRM-Daten | Zoho-ID im Benutzerprofil pruefen; muss der Record-ID im CRM entsprechen |
| "Nicht erlaubte Ziel-URL" | Ziel-URL muss HTTPS und oeffentlich erreichbar sein; ggf. Allowlist konfigurieren |
| 403 "Nur interne Aufrufe" | Endpunkt ist auf intern gestellt; `dgptm_internal_signature_headers()` nutzen |
| Rolle "mitglied" wird nicht gesetzt | Zoho-Feld `aktives_mitglied` im CRM-Record pruefen; Rollenwechsel-Log kontrollieren |
| API-Daten veraltet | Transient-Cache erneuert sich alle 30 Sekunden; ggf. Transients manuell loeschen |

## Technische Details

- **Hooks:**
  - `dgptm_user_data_loaded` -- wird nach dem Laden der Zoho-Daten eines Benutzers ausgeloest (Parameter: `$zoho_data`, `$user_id`)
  - `dgptm_allowed_hosts` -- Filter fuer die Host-Allowlist
  - `dgptm_forward_headers` -- Filter fuer Forward-Header
  - `dgptm_allow_iframe` -- Filter fuer iframe-Rendering von URL-Feldern
- **Datenbank-Tabelle:** `{prefix}_dgptm_role_changes` (Rollenwechsel-Protokoll)
- **Transient-Cache:** `dgptm_zoho_data_{user_id}` (30 Sekunden pro Benutzer)
- **Options:** `dgptm_zoho_api_url`, `dgptm_zoho_client_id`, `dgptm_zoho_client_secret`, `dgptm_zoho_access_token`, `dgptm_zoho_refresh_token`, `dgptm_zoho_token_expires`, `dgptm_debug_log`, `wf_endpoints`
- **Admin-Seiten:** Einstellungen > Zoho API, Benutzer > Rollenwechsel-Log
- **REST-Namespace:** `dgptm/v1`
- **Sicherheit:** SSRF-Haertung (nur HTTPS, keine privaten IPs), HMAC-Signatur fuer interne Aufrufe, sensible Daten werden in Logs geschwärzt
