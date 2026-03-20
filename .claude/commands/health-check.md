---
description: DGPTM Suite Health-Check - Fehler und Warnungen der letzten 24h abrufen
allowed-tools: ["WebFetch", "Read", "Bash", "Grep"]
---

# DGPTM Suite Health-Check

Rufe den Health-Check-Endpoint der DGPTM Suite auf perfusiologie.de ab und analysiere die Ergebnisse.

## Schritte

1. Lies den Health-Check-Token aus der Datei `.claude/health-check-token.local.md` (YAML frontmatter, Feld `token`). Falls die Datei nicht existiert, informiere den User dass er den Token aus WordPress (wp_options: `dgptm_health_check_token`) in `.claude/health-check-token.local.md` hinterlegen muss:

```markdown
---
token: DEIN_TOKEN_HIER
---
```

2. Rufe den Endpoint ab:
   - URL: `https://perfusiologie.de/wp-json/dgptm/v1/health-check?hours=24`
   - Header: `Authorization: Bearer {token}`

3. Analysiere die Antwort und berichte:
   - **Status**: healthy / warning / error / critical
   - **Zusammenfassung**: Anzahl Fehler/Warnungen
   - **Fehler nach Modul**: Welche Module Probleme haben
   - **Fehlgeschlagene Module**: Module die nicht geladen werden konnten
   - **System**: Speicher, Zoho-Verbindung, Cron-Status
   - **Top-Fehler**: Die letzten 5-10 Fehlermeldungen mit Timestamp und Modul

4. Bei Fehlern: Schlage konkrete Fixes vor basierend auf den Fehlermeldungen und dem Codebase-Wissen.

## Ausgabe-Format

```
## DGPTM Suite Health-Check

**Status:** [emoji] [status]
**Zeitraum:** Letzte 24 Stunden
**Suite Version:** X.Y.Z

### Fehler-Zusammenfassung
- Critical: X
- Error: Y
- Warning: Z

### Fehler nach Modul
- modul-name: X Fehler
- ...

### Letzte Fehler
| Zeit | Modul | Nachricht |
|------|-------|-----------|
| ... | ... | ... |

### System-Status
- PHP: X.Y
- Speicher: X MB / Y
- Zoho: verbunden/getrennt
- Cron: aktiv/inaktiv

### Empfohlene Aktionen
1. ...
```
