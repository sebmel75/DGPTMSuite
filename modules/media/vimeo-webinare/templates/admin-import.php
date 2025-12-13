<?php
/**
 * Admin Import Template
 * Batch-Import von Vimeo-Ordnern
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_token = get_option('vimeo_webinar_api_token', '');
?>

<div class="wrap vw-import-wrap">
    <h1>
        <span class="dashicons dashicons-video-alt2"></span>
        Vimeo Batch Import
    </h1>

    <p class="description">
        Importiere alle Videos aus einem Vimeo-Ordner automatisch als Webinare.
    </p>

    <!-- Step 1: API Connection -->
    <div class="vw-import-card">
        <h2>1. Vimeo API Verbindung</h2>

        <div class="vw-import-section">
            <p>
                <strong>Benötigt:</strong> Vimeo Personal Access Token mit Lese-Berechtigung<br>
                <a href="https://developer.vimeo.com/api/authentication" target="_blank">Token erstellen →</a>
            </p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="vimeo-api-token">API Token</label>
                    </th>
                    <td>
                        <input type="text"
                               id="vimeo-api-token"
                               class="regular-text"
                               value="<?php echo esc_attr($current_token); ?>"
                               placeholder="Vimeo Personal Access Token">
                        <button id="test-connection" class="button button-secondary">
                            Verbindung testen
                        </button>
                        <p class="description">
                            Der Token wird sicher in der Datenbank gespeichert.
                        </p>
                        <div id="connection-status" class="vw-status" style="display: none;"></div>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Step 2: Select Folder -->
    <div class="vw-import-card">
        <h2>2. Vimeo-Ordner auswählen</h2>

        <div class="vw-import-section">
            <button id="load-folders" class="button button-primary" disabled>
                <span class="dashicons dashicons-category"></span>
                Ordner laden
            </button>

            <div id="folder-list" class="vw-folder-list" style="display: none;">
                <p><strong>Verfügbare Ordner:</strong></p>
                <select id="folder-select" class="regular-text" size="10">
                    <!-- Folders will be loaded here -->
                </select>
            </div>
        </div>
    </div>

    <!-- Step 3: Import Settings -->
    <div class="vw-import-card">
        <h2>3. Import-Einstellungen</h2>

        <div class="vw-import-section">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="import-category">Kategorie</label>
                    </th>
                    <td>
                        <input type="text"
                               id="import-category"
                               class="regular-text"
                               placeholder="z.B. Kardiologie, Allgemeinmedizin">
                        <p class="description">
                            Wird als Meta-Feld gespeichert (optional)
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="auto-punkte">Fortbildungspunkte</label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   id="auto-punkte"
                                   value="1">
                            Automatisch berechnen (1 Punkt pro 60 Minuten)
                        </label>
                        <br>
                        <label style="margin-top: 10px; display: inline-block;">
                            <span>Standard-Punkte:</span>
                            <input type="number"
                                   id="default-punkte"
                                   value="1"
                                   min="0"
                                   max="10"
                                   style="width: 60px;">
                            <span class="description">(wenn nicht automatisch)</span>
                        </label>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Step 4: Start Import -->
    <div class="vw-import-card">
        <h2>4. Import starten</h2>

        <div class="vw-import-section">
            <p>
                <strong>Hinweis:</strong> Alle Webinare werden zunächst als <strong>Entwurf</strong> erstellt.
                Du kannst sie nach dem Import überprüfen und veröffentlichen.
            </p>

            <button id="start-import" class="button button-hero button-primary" disabled>
                <span class="dashicons dashicons-download"></span>
                Batch-Import starten
            </button>

            <div id="import-progress" class="vw-progress" style="display: none;">
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
                <p class="progress-text">Wird vorbereitet...</p>
            </div>

            <div id="import-results" class="vw-results" style="display: none;">
                <!-- Results will be shown here -->
            </div>
        </div>
    </div>

    <!-- Help Section -->
    <div class="vw-import-card vw-help-card">
        <h2>Hilfe & Hinweise</h2>

        <div class="vw-import-section">
            <h3>Wie funktioniert der Import?</h3>
            <ol>
                <li>Vimeo Personal Access Token erstellen (mit "Private"-Berechtigung)</li>
                <li>Token eingeben und Verbindung testen</li>
                <li>Ordner aus deinem Vimeo-Account auswählen</li>
                <li>Optional: Kategorie und Fortbildungspunkte festlegen</li>
                <li>Import starten - Videos werden als Entwürfe erstellt</li>
            </ol>

            <h3>Was wird importiert?</h3>
            <ul>
                <li>✓ Videotitel</li>
                <li>✓ Videobeschreibung</li>
                <li>✓ Vimeo-ID und URL</li>
                <li>✓ Video-Dauer</li>
                <li>✓ Vorschaubild (Thumbnail)</li>
                <li>✓ Fortbildungspunkte (berechnet oder standard)</li>
                <li>✓ Kategorie (falls angegeben)</li>
            </ul>

            <h3>Duplikate</h3>
            <p>
                Videos, die bereits als Webinar existieren (gleiche Vimeo-ID),
                werden automatisch übersprungen.
            </p>

            <h3>Fehlerbehandlung</h3>
            <p>
                Bei Fehlern während des Imports wird eine detaillierte Fehler-Liste angezeigt.
                Bereits importierte Videos bleiben erhalten.
            </p>
        </div>
    </div>
</div>

<style>
.vw-import-wrap {
    max-width: 1200px;
}

.vw-import-wrap h1 {
    display: flex;
    align-items: center;
    gap: 10px;
}

.vw-import-wrap h1 .dashicons {
    font-size: 32px;
    width: 32px;
    height: 32px;
}

.vw-import-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    margin: 20px 0;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    border-radius: 4px;
}

.vw-import-card h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #e0e0e0;
}

.vw-import-section {
    margin-top: 15px;
}

.vw-status {
    margin-top: 10px;
    padding: 10px;
    border-radius: 4px;
}

.vw-status.success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.vw-status.error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.vw-folder-list {
    margin-top: 15px;
}

#folder-select {
    width: 100%;
    max-width: 600px;
}

.vw-progress {
    margin-top: 20px;
}

.progress-bar {
    width: 100%;
    height: 30px;
    background: #f0f0f1;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    overflow: hidden;
    position: relative;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #2271b1 0%, #135e96 100%);
    width: 0%;
    transition: width 0.3s ease;
}

.progress-text {
    margin-top: 10px;
    text-align: center;
    font-weight: bold;
}

.vw-results {
    margin-top: 20px;
    padding: 15px;
    background: #f9f9f9;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
}

.vw-results h3 {
    margin-top: 0;
}

.vw-results ul {
    margin: 10px 0;
    padding-left: 20px;
}

.vw-results .success-list li {
    color: #155724;
}

.vw-results .skip-list li {
    color: #856404;
}

.vw-results .error-list li {
    color: #721c24;
}

.vw-help-card {
    background: #f0f6fc;
    border-color: #2271b1;
}

.vw-help-card h3 {
    margin-top: 20px;
    margin-bottom: 10px;
}

.vw-help-card ul,
.vw-help-card ol {
    margin: 10px 0;
    padding-left: 25px;
}

.vw-help-card li {
    margin: 5px 0;
    line-height: 1.6;
}

.button-hero .dashicons {
    vertical-align: middle;
    margin-top: -3px;
    margin-right: 5px;
}
</style>
