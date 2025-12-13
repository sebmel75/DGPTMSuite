# Sicherheitskonfiguration - DGPTM Plugin Suite

## Übersicht

Dieses Dokument beschreibt die Sicherheitsmaßnahmen für das automatische Deployment.

---

## 1. GitHub Environment Protection (Empfohlen)

Schützt das Production-Deployment durch manuelle Genehmigung.

### Einrichtung

1. Gehe zu: **https://github.com/sebmel75/DGPTMSuite/settings/environments**
2. Klicke auf **New environment**
3. Name: `production`
4. Klicke auf **Configure environment**

### Schutzregeln aktivieren

Aktiviere folgende Optionen:

| Option | Empfehlung |
|--------|-----------|
| **Required reviewers** | ✅ Aktivieren - Füge dich selbst hinzu |
| **Wait timer** | Optional: 1-5 Minuten Wartezeit |
| **Deployment branches** | `main` only |

### Ergebnis

Nach der Einrichtung:
- Jedes Deployment erfordert deine Genehmigung
- Du erhältst eine E-Mail-Benachrichtigung
- Du kannst im GitHub UI genehmigen oder ablehnen

---

## 2. Branch Protection Rules

Schützt den `main` Branch vor unautorisierten Änderungen.

### Einrichtung

1. Gehe zu: **https://github.com/sebmel75/DGPTMSuite/settings/branches**
2. Klicke auf **Add branch protection rule**
3. Branch name pattern: `main`

### Empfohlene Einstellungen

| Option | Aktivieren? | Beschreibung |
|--------|-------------|--------------|
| **Require a pull request before merging** | ✅ Ja | Verhindert direktes Pushen |
| **Require approvals** | ✅ 1 | Du musst PRs genehmigen |
| **Dismiss stale approvals** | ✅ Ja | Bei neuen Commits neu genehmigen |
| **Require status checks** | ✅ Ja | Tests müssen bestehen |
| **Require branches to be up to date** | ✅ Ja | Branch muss aktuell sein |
| **Include administrators** | Optional | Auch für dich selbst? |

### Ergebnis

Nach der Einrichtung:
- Niemand kann direkt auf `main` pushen
- Alle Änderungen müssen über Pull Request
- Tests müssen erfolgreich sein
- Du musst jeden PR genehmigen

---

## 3. Secrets-Sicherheit

### Was GitHub Secrets schützt

- ✅ Secrets sind verschlüsselt gespeichert
- ✅ Nicht in Logs sichtbar (automatisch maskiert)
- ✅ Nicht verfügbar für Forks/PRs von externen Contributors
- ✅ Nur für Workflows aus dem Haupt-Repository

### Aktuelle Secrets

| Secret | Zweck |
|--------|-------|
| `SSH_HOST` | Server IP |
| `SSH_USER` | SSH Benutzername |
| `SSH_PASSWORD` | SSH Passwort |
| `WP_PATH` | WordPress Pfad |

### Best Practices

1. **Passwort regelmäßig ändern** (alle 90 Tage)
2. **Keine Secrets in Code** - nur in GitHub Secrets
3. **Audit Log prüfen** - Settings → Audit log

---

## 4. Workflow-Sicherheit

### Implementierte Maßnahmen

```yaml
# Minimale Berechtigungen
permissions:
  contents: read
  actions: read

# Environment Protection
environment:
  name: production
```

### Was der Workflow NICHT tun kann

- ❌ Repository-Einstellungen ändern
- ❌ Secrets auslesen und anzeigen
- ❌ Andere Branches modifizieren
- ❌ Issues/PRs erstellen oder ändern

---

## 5. Wer kann deployen?

| Rolle | Push auf main | Manueller Deploy | Environment Approval |
|-------|--------------|------------------|---------------------|
| Owner (du) | ✅ Ja* | ✅ Ja | ✅ Ja |
| Collaborator (Write) | ✅ Ja* | ✅ Ja | ❌ Nein (nur Reviewer) |
| Collaborator (Read) | ❌ Nein | ❌ Nein | ❌ Nein |
| Externe (Fork) | ❌ Nein | ❌ Nein | ❌ Nein |

*Mit Branch Protection: Nur über genehmigte PRs

---

## 6. Notfall: Deployment stoppen

### Laufendes Deployment abbrechen

1. Gehe zu: https://github.com/sebmel75/DGPTMSuite/actions
2. Klicke auf den laufenden Workflow
3. Klicke auf **Cancel workflow**

### Alle Deployments pausieren

1. Gehe zu: Settings → Environments → production
2. **Pause deployments** aktivieren

### Workflow komplett deaktivieren

1. Gehe zu: Actions → Deploy DGPTM Plugin Suite
2. Klicke auf **...** → **Disable workflow**

---

## 7. Audit & Monitoring

### Deployment-Historie

- **GitHub Actions**: https://github.com/sebmel75/DGPTMSuite/actions
- **Deployments**: https://github.com/sebmel75/DGPTMSuite/deployments

### Benachrichtigungen aktivieren

1. Settings → Notifications
2. **Actions** → Workflow runs aktivieren
3. Optional: E-Mail bei fehlgeschlagenen Workflows

---

## Schnellstart: Minimale Absicherung

**5 Minuten Setup für grundlegende Sicherheit:**

1. ✅ Environment `production` erstellen
2. ✅ Required reviewer hinzufügen (dich selbst)
3. ✅ Branch protection für `main` aktivieren
4. ✅ "Require pull request" aktivieren

**Danach:** Jedes Deployment erfordert deine explizite Genehmigung.
