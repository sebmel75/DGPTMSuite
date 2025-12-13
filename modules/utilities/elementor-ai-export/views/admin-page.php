<?php
if (!defined('ABSPATH')) {
    exit;
}

$api_configured = !empty(get_option('elementor_ai_export_claude_api_key', ''));
?>
<div class="wrap elementor-ai-export-wrap">
    <h1>Elementor AI Export</h1>

    <p class="description">
        Exportieren Sie Elementor-Seiten in ein Claude-freundliches Format. Claude kann dann die Inhalte verstehen und nach Ihren Wünschen anpassen.
        <?php if ($api_configured): ?>
            <span class="api-status api-configured">✓ Claude API konfiguriert</span>
        <?php else: ?>
            <span class="api-status api-not-configured">⚠ Claude API nicht konfiguriert - <a href="<?php echo admin_url('admin.php?page=elementor-ai-export-settings'); ?>">Jetzt einrichten</a></span>
        <?php endif; ?>
    </p>

    <h2 class="nav-tab-wrapper">
        <a href="#" class="nav-tab nav-tab-active" data-tab="manual">Manuell (Export/Import)</a>
        <a href="#" class="nav-tab" data-tab="auto">Automatisches Redesign</a>
        <a href="#" class="nav-tab" data-tab="staging">Staging-Verwaltung</a>
    </h2>

    <div class="tab-content" id="tab-manual">
    <div class="elementor-ai-export-container">
        <!-- Export Section -->
        <div class="export-section card">
            <h2>Export</h2>

            <div class="export-controls">
                <div class="control-group">
                    <label for="page-select">Seite auswählen:</label>
                    <select id="page-select" class="regular-text">
                        <option value="">Laden...</option>
                    </select>
                </div>

                <div class="control-group">
                    <label for="format-select">Export-Format:</label>
                    <select id="format-select" class="regular-text">
                        <option value="markdown">Markdown (empfohlen für Claude)</option>
                        <option value="json">JSON (für Re-Import)</option>
                        <option value="yaml">YAML</option>
                    </select>
                </div>

                <button id="export-btn" class="button button-primary" disabled>
                    <span class="dashicons dashicons-download"></span>
                    Seite exportieren
                </button>
            </div>

            <div id="export-result" class="export-result" style="display:none;">
                <h3>Export erfolgreich</h3>
                <div class="export-preview">
                    <textarea id="export-content" readonly></textarea>
                </div>
                <div class="export-actions">
                    <button id="copy-btn" class="button">
                        <span class="dashicons dashicons-clipboard"></span>
                        In Zwischenablage kopieren
                    </button>
                    <button id="download-btn" class="button">
                        <span class="dashicons dashicons-download"></span>
                        Als Datei herunterladen
                    </button>
                </div>
            </div>
        </div>

        <!-- Import Section -->
        <div class="import-section card">
            <h2>Import</h2>

            <p class="description">
                Nachdem Claude Ihre Seite bearbeitet hat, können Sie die geänderte Version hier importieren.
                <strong>Wichtig:</strong> Nur JSON-Format wird beim Import unterstützt!
            </p>

            <div class="import-controls">
                <div class="control-group">
                    <label for="import-page-select">Ziel-Seite:</label>
                    <select id="import-page-select" class="regular-text">
                        <option value="">Laden...</option>
                    </select>
                </div>

                <div class="control-group">
                    <label for="import-content">Bearbeiteter Inhalt (JSON):</label>
                    <textarea id="import-content" rows="10" class="large-text code" placeholder="Fügen Sie hier den von Claude bearbeiteten JSON-Inhalt ein..."></textarea>
                </div>

                <div class="control-group">
                    <label>
                        <input type="radio" name="import-mode" value="staging" checked>
                        <strong>Als Staging-Seite importieren</strong> (empfohlen)
                    </label>
                    <p class="description" style="margin: 5px 0 10px 25px;">
                        Erstellt eine Entwurfs-Kopie zum Testen. Sie können die Änderungen später auf die Original-Seite übertragen.
                    </p>

                    <label>
                        <input type="radio" name="import-mode" value="direct">
                        <strong>Direkt überschreiben</strong>
                    </label>
                    <p class="description" style="margin: 5px 0 0 25px;">
                        Überschreibt die Original-Seite sofort. Ein Backup wird erstellt.
                    </p>
                </div>

                <div class="import-warning">
                    <span class="dashicons dashicons-warning"></span>
                    <span id="import-warning-text">
                        <strong>Hinweis:</strong> Eine Staging-Seite wird als Entwurf erstellt. Sie können sie testen, bevor Sie die Änderungen auf die Original-Seite übertragen.
                    </span>
                </div>

                <button id="import-btn" class="button button-primary" disabled>
                    <span class="dashicons dashicons-upload"></span>
                    <span id="import-btn-text">Als Staging importieren</span>
                </button>
            </div>

            <div id="import-result" class="import-result" style="display:none;"></div>
        </div>
    </div>
    </div>

    <!-- Automatisches Redesign Tab -->
    <div class="tab-content" id="tab-auto" style="display:none;">
        <div class="auto-redesign-container">
            <?php if (!$api_configured): ?>
            <div class="notice notice-warning">
                <p>
                    <strong>Claude API nicht konfiguriert!</strong>
                    Bitte <a href="<?php echo admin_url('admin.php?page=elementor-ai-export-settings'); ?>">konfigurieren Sie erst Ihren API Key</a>,
                    um die automatische Umgestaltung nutzen zu können.
                </p>
            </div>
            <?php endif; ?>

            <div class="card">
                <h2>Automatisches Redesign mit Claude API</h2>

                <p class="description">
                    Lassen Sie Claude Ihre Elementor-Seite vollautomatisch umgestalten - ohne manuelles Copy-Paste!
                </p>

                <div class="auto-controls">
                    <div class="control-group">
                        <label for="auto-page-select">Seite auswählen:</label>
                        <select id="auto-page-select" class="regular-text" <?php echo !$api_configured ? 'disabled' : ''; ?>>
                            <option value="">Laden...</option>
                        </select>
                    </div>

                    <div class="control-group">
                        <label for="redesign-prompt">Umgestaltungs-Anweisungen:</label>
                        <textarea
                            id="redesign-prompt"
                            rows="8"
                            class="large-text"
                            placeholder="Beispiele:&#10;- Ändere alle Überschriften zu einem professionelleren Ton&#10;- Mache die Farben moderner (Blau statt Grün)&#10;- Füge mehr Call-to-Action-Elemente hinzu&#10;- Gestalte die Seite für ein jüngeres Publikum um&#10;- Optimiere für Conversion (mehr Buttons, klarere Struktur)"
                            <?php echo !$api_configured ? 'disabled' : ''; ?>
                        ></textarea>
                        <p class="description">
                            Beschreiben Sie, wie Claude die Seite umgestalten soll. Seien Sie so spezifisch wie möglich!
                        </p>
                    </div>

                    <div class="control-group">
                        <label>
                            <input type="checkbox" id="auto-duplicate" <?php echo !$api_configured ? 'disabled' : ''; ?>>
                            Seite zuerst duplizieren (empfohlen)
                        </label>
                        <p class="description" style="margin-left: 25px;">
                            Erstellt eine Kopie der Original-Seite, bevor Änderungen vorgenommen werden.
                        </p>
                    </div>

                    <button id="auto-redesign-btn" class="button button-primary button-hero" disabled>
                        <span class="dashicons dashicons-admin-customizer"></span>
                        Automatisch umgestalten mit Claude
                    </button>

                    <div id="auto-progress" class="auto-progress" style="display:none;">
                        <div class="progress-bar">
                            <div class="progress-fill"></div>
                        </div>
                        <p class="progress-text">Seite wird umgestaltet...</p>
                    </div>
                </div>

                <div id="auto-result" class="auto-result" style="display:none;"></div>
            </div>

            <div class="card">
                <h2>Wie funktioniert es?</h2>

                <div class="workflow-steps">
                    <div class="workflow-step">
                        <div class="step-icon">1</div>
                        <div class="step-content">
                            <h3>Seite wird exportiert</h3>
                            <p>Das System exportiert die ausgewählte Seite automatisch als JSON mit allen Elementor-Einstellungen.</p>
                        </div>
                    </div>

                    <div class="workflow-step">
                        <div class="step-icon">2</div>
                        <div class="step-content">
                            <h3>Claude gestaltet um</h3>
                            <p>Ihre Anweisungen werden an Claude gesendet. Claude analysiert die Seite und nimmt die gewünschten Änderungen vor.</p>
                        </div>
                    </div>

                    <div class="workflow-step">
                        <div class="step-icon">3</div>
                        <div class="step-content">
                            <h3>Staging-Seite erstellt</h3>
                            <p>Das Redesign wird automatisch als Staging-Seite (Entwurf) importiert - sicher zum Testen!</p>
                        </div>
                    </div>

                    <div class="workflow-step">
                        <div class="step-icon">4</div>
                        <div class="step-content">
                            <h3>Testen & Übernehmen</h3>
                            <p>Sie prüfen die Staging-Seite. Bei Zufriedenheit übernehmen Sie die Änderungen auf die Original-Seite.</p>
                        </div>
                    </div>
                </div>

                <div class="tips-box">
                    <h3>Tipps für beste Ergebnisse</h3>
                    <ul>
                        <li><strong>Spezifisch sein:</strong> "Ändere Farbe zu #2271b1" statt "Mach es blauer"</li>
                        <li><strong>Struktur beschreiben:</strong> Claude kann Elemente hinzufügen, entfernen, neu anordnen</li>
                        <li><strong>Testen:</strong> Nutzen Sie immer die Staging-Funktion zum Testen</li>
                        <li><strong>Iterativ arbeiten:</strong> Mehrere kleine Änderungen oft besser als eine große</li>
                    </ul>

                    <h3 style="margin-top: 20px;">⏱️ Hinweis zu Rate Limits</h3>
                    <p>
                        Beim <strong>ersten Request nach längerer Pause</strong> kann es sein, dass die Claude API ein Rate Limit meldet.
                        Das ist normal - Anthropic skaliert Ihr Token-Limit gerade von 50.000 auf 120.000 pro Minute hoch.
                    </p>
                    <p>
                        <strong>Lösung:</strong> Einfach 60 Sekunden warten und dann erneut auf "Automatisch umgestalten" klicken.
                        Beim zweiten Versuch funktioniert es dann!
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Staging-Verwaltung Tab -->
    <div class="tab-content" id="tab-staging" style="display:none;">
        <!-- Staging Management -->
        <div class="staging-section card">
            <h2>Staging-Seiten verwalten</h2>

            <p class="description">
                Hier sehen Sie alle Staging-Seiten, die Sie zum Testen erstellt haben.
            </p>

            <div id="staging-list">
                <p class="loading">Lade Staging-Seiten...</p>
            </div>
        </div>

        <!-- Instructions -->
        <div class="instructions-section card">
            <h2>Anleitung</h2>

            <div class="instruction-steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h3>Seite exportieren</h3>
                        <p>Wählen Sie eine Elementor-Seite und exportieren Sie sie als Markdown oder JSON.</p>
                    </div>
                </div>

                <div class="step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h3>Mit Claude bearbeiten</h3>
                        <p>Öffnen Sie <a href="https://claude.ai" target="_blank">claude.ai</a> und geben Sie klare Anweisungen mit der exportierten Datei.</p>
                    </div>
                </div>

                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h3>Als Staging importieren</h3>
                        <p>Importieren Sie Claudes JSON-Ausgabe als Staging-Seite zum Testen.</p>
                    </div>
                </div>

                <div class="step">
                    <div class="step-number">4</div>
                    <div class="step-content">
                        <h3>Testen und übernehmen</h3>
                        <p>Prüfen Sie die Staging-Seite. Bei Zufriedenheit übernehmen Sie die Änderungen.</p>
                    </div>
                </div>
            </div>

            <div class="tips">
                <h3>Tipps für beste Ergebnisse</h3>
                <ul>
                    <li><strong>Staging-Import empfohlen:</strong> Testen Sie Änderungen erst als Staging-Seite, bevor Sie sie übernehmen</li>
                    <li><strong>Markdown für Verständnis:</strong> Nutzen Sie Markdown-Export, damit Claude die Struktur gut versteht</li>
                    <li><strong>JSON für Import:</strong> Für den Re-Import brauchen Sie JSON-Format</li>
                    <li><strong>Klare Anweisungen:</strong> Sagen Sie Claude genau, welche Bereiche geändert werden sollen</li>
                    <li><strong>IDs beibehalten:</strong> Claude sollte die Element-IDs nicht ändern</li>
                    <li><strong>Backup vorhanden:</strong> Vor jedem Import wird automatisch ein Backup erstellt</li>
                </ul>
            </div>
        </div>
    </div>
    </div>
</div>
