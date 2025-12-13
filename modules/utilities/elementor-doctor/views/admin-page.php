<?php
/**
 * Admin Page Template for Elementor Doctor
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap elementor-doctor-wrap">
    <h1>
        <span class="dashicons dashicons-admin-tools"></span>
        Elementor Doctor
    </h1>

    <p class="description">
        Scannt und repariert fehlerhafte Elementor-Seiten. Alle Reparaturen werden automatisch gesichert.
    </p>

    <div class="elementor-doctor-tabs">
        <nav class="nav-tab-wrapper">
            <a href="#scan" class="nav-tab nav-tab-active">Scannen</a>
            <a href="#batch" class="nav-tab">Stapelverarbeitung</a>
            <a href="#backups" class="nav-tab">Backups</a>
            <a href="#help" class="nav-tab">Hilfe</a>
        </nav>
    </div>

    <!-- Scan Tab -->
    <div id="scan" class="elementor-doctor-tab-content active">
        <div class="elementor-doctor-card">
            <h2>Einzelne Seite scannen</h2>

            <div class="scan-controls">
                <label for="page-selector">
                    <strong>Seite auswählen:</strong>
                </label>
                <select id="page-selector" class="large-text">
                    <option value="">-- Bitte wählen --</option>
                    <?php
                    $pages = get_posts([
                        'post_type' => ['page', 'post'],
                        'post_status' => 'any',
                        'posts_per_page' => -1,
                        'meta_query' => [
                            [
                                'key' => '_elementor_edit_mode',
                                'value' => 'builder',
                                'compare' => '='
                            ]
                        ],
                        'orderby' => 'title',
                        'order' => 'ASC'
                    ]);

                    foreach ($pages as $page) {
                        $status = $page->post_status !== 'publish' ? " ({$page->post_status})" : '';
                        echo '<option value="' . esc_attr($page->ID) . '">' . esc_html($page->post_title . $status) . '</option>';
                    }
                    ?>
                </select>
                <button id="scan-single" class="button button-primary">
                    <span class="dashicons dashicons-search"></span>
                    Scannen
                </button>
            </div>

            <div id="single-scan-results" class="scan-results" style="display: none;">
                <!-- Results will be inserted here -->
            </div>
        </div>
    </div>

    <!-- Batch Tab -->
    <div id="batch" class="elementor-doctor-tab-content">
        <div class="elementor-doctor-card">
            <h2>Alle Elementor-Seiten scannen</h2>

            <p>
                Scannt alle Seiten, die mit Elementor erstellt wurden.
                Der Scan erfolgt in kleinen Stapeln, um Timeouts zu vermeiden.
            </p>

            <div class="batch-controls">
                <button id="scan-all" class="button button-primary button-hero">
                    <span class="dashicons dashicons-search"></span>
                    Alle Seiten scannen
                </button>
                <button id="repair-all" class="button button-secondary button-hero" style="display: none;">
                    <span class="dashicons dashicons-admin-tools"></span>
                    Alle fehlerhaften Seiten reparieren
                </button>
            </div>

            <div id="batch-progress" class="batch-progress" style="display: none;">
                <div class="progress-bar-wrapper">
                    <div class="progress-bar">
                        <div class="progress-bar-fill"></div>
                    </div>
                    <span class="progress-text">0%</span>
                </div>
                <p class="progress-status">Wird geladen...</p>
            </div>

            <div id="batch-stats" class="batch-stats" style="display: none;">
                <div class="stat-box stat-total">
                    <span class="stat-number">0</span>
                    <span class="stat-label">Gescannt</span>
                </div>
                <div class="stat-box stat-valid">
                    <span class="stat-number">0</span>
                    <span class="stat-label">Fehlerfrei</span>
                </div>
                <div class="stat-box stat-errors">
                    <span class="stat-number">0</span>
                    <span class="stat-label">Mit Fehlern</span>
                </div>
                <div class="stat-box stat-warnings">
                    <span class="stat-number">0</span>
                    <span class="stat-label">Mit Warnungen</span>
                </div>
            </div>

            <div id="batch-results" class="batch-results">
                <!-- Results table will be inserted here -->
            </div>
        </div>
    </div>

    <!-- Backups Tab -->
    <div id="backups" class="elementor-doctor-tab-content">
        <div class="elementor-doctor-card">
            <h2>Backup-Verwaltung</h2>

            <p>
                Alle Backups werden vor Reparaturen automatisch erstellt.
                Du kannst sie hier einsehen und wiederherstellen.
            </p>

            <div class="backup-controls" style="margin: 20px 0;">
                <button id="load-all-backups" class="button button-primary">
                    <span class="dashicons dashicons-backup"></span>
                    Alle Backups laden
                </button>
                <button id="refresh-backups" class="button" style="display: none;">
                    <span class="dashicons dashicons-update"></span>
                    Aktualisieren
                </button>
            </div>

            <div id="backup-loading" style="display: none; text-align: center; padding: 20px;">
                <span class="spinner is-active" style="float: none; margin: 0;"></span>
                <p>Lade Backups...</p>
            </div>

            <div id="backup-list">
                <p class="description">Klicke auf "Alle Backups laden" um alle gespeicherten Backups anzuzeigen.</p>
            </div>
        </div>
    </div>

    <!-- Help Tab -->
    <div id="help" class="elementor-doctor-tab-content">
        <div class="elementor-doctor-card">
            <h2>Hilfe & Dokumentation</h2>

            <h3>Was macht Elementor Doctor?</h3>
            <p>
                Elementor Doctor scannt deine Elementor-Seiten nach häufigen Fehlern und Problemen und kann diese automatisch reparieren.
            </p>

            <h3>Erkannte Fehler</h3>
            <ul>
                <li><strong>Ungültiges JSON:</strong> Beschädigte JSON-Daten werden repariert</li>
                <li><strong>Fehlende Element-IDs:</strong> IDs werden automatisch generiert</li>
                <li><strong>Doppelte IDs:</strong> Konflikte werden durch neue IDs aufgelöst</li>
                <li><strong>Defekte Struktur:</strong> Hierarchie wird korrigiert (Section → Column → Widget)</li>
                <li><strong>Fehlende Meta-Daten:</strong> Edit-Mode und Version werden ergänzt</li>
                <li><strong>Defekte Bild-Referenzen:</strong> Nicht existierende Bilder werden entfernt</li>
                <li><strong>CSS-Probleme:</strong> CSS-Cache wird regeneriert</li>
                <li><strong>Bearbeitungssperren:</strong> Hängende Locks werden entfernt</li>
            </ul>

            <h3>Sicherheit</h3>
            <p>
                <strong>Vor jeder Reparatur wird automatisch ein Backup erstellt!</strong>
                Das Backup enthält alle Elementor-Daten und kann jederzeit wiederhergestellt werden.
            </p>

            <h3>Batch-Verarbeitung</h3>
            <p>
                Der Batch-Scan verarbeitet alle Seiten in kleinen Stapeln (10 Seiten pro Durchlauf),
                um Server-Timeouts zu vermeiden. Der Fortschritt wird in Echtzeit angezeigt.
            </p>

            <h3>Empfohlener Workflow</h3>
            <ol>
                <li>Starte einen kompletten Scan aller Seiten</li>
                <li>Prüfe die Ergebnisse und identifiziere fehlerhafte Seiten</li>
                <li>Teste die Reparatur an einer einzelnen Seite</li>
                <li>Prüfe das Ergebnis im Elementor-Editor</li>
                <li>Wenn erfolgreich: Repariere alle fehlerhaften Seiten per Stapelverarbeitung</li>
            </ol>

            <h3>Backups wiederherstellen</h3>
            <p>
                Im "Backups"-Tab kannst du alle erstellten Backups einsehen und wiederherstellen.
                Backups werden als eigener Post-Type gespeichert und sind vor versehentlichem Löschen geschützt.
            </p>

            <div class="notice notice-warning inline">
                <p>
                    <strong>Hinweis:</strong> Obwohl Backups automatisch erstellt werden,
                    wird empfohlen, vor umfangreichen Reparaturen ein vollständiges Datenbank-Backup zu erstellen.
                </p>
            </div>
        </div>
    </div>
</div>

<style>
.elementor-doctor-wrap h1 .dashicons {
    font-size: 32px;
    width: 32px;
    height: 32px;
    vertical-align: middle;
}

.elementor-doctor-tabs {
    margin: 20px 0;
}

.elementor-doctor-tab-content {
    display: none;
}

.elementor-doctor-tab-content.active {
    display: block;
}

.elementor-doctor-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    margin: 20px 0;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.scan-controls {
    margin: 20px 0;
}

.scan-controls label {
    display: block;
    margin-bottom: 10px;
}

.scan-controls select {
    display: block;
    margin-bottom: 10px;
}

.batch-controls {
    margin: 20px 0;
    text-align: center;
}

.batch-controls .button-hero {
    margin: 0 10px;
}

.batch-progress {
    margin: 30px 0;
}

.progress-bar-wrapper {
    position: relative;
    margin-bottom: 10px;
}

.progress-bar {
    height: 30px;
    background: #f0f0f1;
    border: 1px solid #ccd0d4;
    border-radius: 3px;
    overflow: hidden;
}

.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #2271b1 0%, #135e96 100%);
    width: 0%;
    transition: width 0.3s ease;
}

.progress-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-weight: bold;
    color: #1d2327;
}

.progress-status {
    text-align: center;
    font-size: 14px;
    color: #646970;
}

.batch-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin: 30px 0;
}

.stat-box {
    background: #f9f9f9;
    border: 1px solid #e0e0e0;
    border-radius: 5px;
    padding: 20px;
    text-align: center;
}

.stat-number {
    display: block;
    font-size: 32px;
    font-weight: bold;
    color: #1d2327;
}

.stat-label {
    display: block;
    font-size: 14px;
    color: #646970;
    margin-top: 5px;
}

.stat-total .stat-number { color: #2271b1; }
.stat-valid .stat-number { color: #00a32a; }
.stat-errors .stat-number { color: #d63638; }
.stat-warnings .stat-number { color: #dba617; }

.scan-results {
    margin-top: 20px;
}

.result-header {
    padding: 15px;
    background: #f9f9f9;
    border-left: 4px solid #2271b1;
    margin-bottom: 15px;
}

.result-header.has-errors {
    border-left-color: #d63638;
    background: #fcf0f1;
}

.result-header h3 {
    margin: 0 0 5px 0;
}

.result-details {
    margin-top: 15px;
}

.error-list, .warning-list, .info-list {
    list-style: none;
    margin: 10px 0;
    padding: 0;
}

.error-list li, .warning-list li, .info-list li {
    padding: 8px 12px;
    margin: 5px 0;
    border-radius: 3px;
}

.error-list li {
    background: #fcf0f1;
    border-left: 3px solid #d63638;
}

.warning-list li {
    background: #fcf9e8;
    border-left: 3px solid #dba617;
}

.info-list li {
    background: #f0f6fc;
    border-left: 3px solid #2271b1;
}

.result-actions {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #e0e0e0;
}

.batch-results table {
    width: 100%;
    margin-top: 20px;
}

.batch-results .status-icon {
    font-size: 20px;
    width: 20px;
    height: 20px;
}

.batch-results .status-icon.valid { color: #00a32a; }
.batch-results .status-icon.error { color: #d63638; }
.batch-results .status-icon.warning { color: #dba617; }
</style>
