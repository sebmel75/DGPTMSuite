# DGPTM Plugin Suite - Deployment Setup

Dieses Dokument beschreibt die Einrichtung des automatischen Deployments zu perfusiologie.de.

## Übersicht

```
Claude Code → Git Push → GitHub Actions → Tests → Backup → Deploy → perfusiologie.de
```

Bei jedem Push auf den `main` Branch:
1. PHP Syntax wird geprüft
2. Kritische Dateien werden verifiziert
3. Backup der aktuellen Version wird erstellt
4. Neue Version wird deployed
5. Deployment wird verifiziert

---

## GitHub Secrets einrichten

### Schritt 1: GitHub Repository öffnen

1. Gehe zu: https://github.com/sebmel75/DGPTMSuite
2. Klicke auf **Settings** (Zahnrad-Symbol)
3. Im linken Menü: **Secrets and variables** → **Actions**
4. Klicke auf **New repository secret**

### Schritt 2: Folgende Secrets anlegen

| Secret Name | Wert | Beschreibung |
|-------------|------|--------------|
| `SSH_HOST` | `81.27.231.84` | Server IP-Adresse |
| `SSH_USER` | `Sebmel75` | SSH Benutzername |
| `SSH_PASSWORD` | `[Dein SSH Passwort]` | SSH Passwort (sicher eingeben!) |
| `WP_PATH` | `/httpdocs` | WordPress Root-Pfad (ohne trailing slash) |

### Schritt 3: Secrets einzeln hinzufügen

Für jedes Secret:
1. **Name:** Den Namen aus der Tabelle eingeben (z.B. `SSH_HOST`)
2. **Secret:** Den entsprechenden Wert eingeben
3. **Add secret** klicken

---

## Workflow testen

### Manueller Test

1. Gehe zu: https://github.com/sebmel75/DGPTMSuite/actions
2. Wähle **Deploy DGPTM Plugin Suite**
3. Klicke auf **Run workflow** → **Run workflow**
4. Beobachte den Fortschritt

### Automatischer Test

Jeder Push auf `main` löst automatisch das Deployment aus:

```bash
git add .
git commit -m "Test deployment"
git push origin main
```

---

## Backup-Verzeichnis

Backups werden auf dem Server gespeichert unter:
```
~/backups/dgptm-backup-YYYYMMDD-HHMMSS.tar.gz
```

### Backup wiederherstellen

```bash
ssh Sebmel75@81.27.231.84
cd ~/backups
ls -la  # Verfügbare Backups anzeigen
tar -xzf dgptm-backup-YYYYMMDD-HHMMSS.tar.gz -C /httpdocs/wp-content/plugins/
```

---

## Workflow-Ablauf

```yaml
Jobs:
  1. test          # PHP Syntax-Check
  2. deploy        # Backup + Deployment (nur nach erfolgreichen Tests)
  3. notify        # Status-Ausgabe
```

### Was wird deployed?

Alle Dateien außer:
- `.git/` - Git-Verzeichnis
- `.github/` - GitHub Workflows
- `*.md` - Markdown-Dokumentation
- `.claude/` - Claude Code Einstellungen
- `exports/` - Generierte Exports
- `*.log` - Log-Dateien

---

## Fehlerbehebung

### Deployment schlägt fehl

1. **Prüfe GitHub Actions Logs:**
   - https://github.com/sebmel75/DGPTMSuite/actions
   - Klicke auf den fehlgeschlagenen Run
   - Prüfe die Fehlerdetails

2. **Häufige Fehler:**
   - `Permission denied` → SSH-Credentials prüfen
   - `No such file or directory` → WP_PATH prüfen
   - `Connection refused` → Server/Firewall prüfen

### Manuelles Deployment (Notfall)

```bash
# Lokal
cd dgptm-plugin-suite
rsync -avz --delete \
  --exclude='.git' \
  --exclude='.github' \
  --exclude='exports/' \
  --exclude='*.log' \
  ./ Sebmel75@81.27.231.84:/httpdocs/wp-content/plugins/dgptm-plugin-suite/
```

---

## Sicherheitshinweise

1. **Passwort nie im Code speichern** - Immer GitHub Secrets verwenden
2. **Secrets regelmäßig rotieren** - Alle 90 Tage empfohlen
3. **Backups prüfen** - Regelmäßig testen ob Backups funktionieren
4. **Logs überwachen** - GitHub Actions Logs bei Problemen prüfen

---

## Kontakt

Bei Problemen:
- GitHub Issues: https://github.com/sebmel75/DGPTMSuite/issues
- DGPTM: https://www.dgptm.de/
