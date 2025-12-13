<?php
/**
 * Module Metadata Modal
 * UI für Flags, Kommentare und Versionsumschaltung
 */

if (!defined('ABSPATH')) {
    exit;
}

// Hole Metadata-Manager
$metadata = DGPTM_Module_Metadata_File::get_instance();
?>

<!-- Module Metadata Modal -->
<div id="dgptm-metadata-modal" class="dgptm-modal" style="display:none;">
    <div class="dgptm-modal-overlay"></div>
    <div class="dgptm-modal-content">
        <div class="dgptm-modal-header">
            <h2><?php _e('Modul-Informationen', 'dgptm-suite'); ?></h2>
            <button class="dgptm-modal-close" type="button">&times;</button>
        </div>

        <div class="dgptm-modal-body">
            <!-- Module Info -->
            <div class="dgptm-metadata-section">
                <h3 class="dgptm-module-title" id="dgptm-metadata-module-name"></h3>
                <p class="dgptm-module-id">ID: <code id="dgptm-metadata-module-id"></code></p>
            </div>

            <!-- Flags Section -->
            <div class="dgptm-metadata-section dgptm-flags-section">
                <h3><?php _e('Flags', 'dgptm-suite'); ?></h3>

                <!-- Current Flags -->
                <div class="dgptm-current-flags" id="dgptm-current-flags">
                    <p class="dgptm-no-flags" style="display:none;">
                        <?php _e('Keine Flags gesetzt', 'dgptm-suite'); ?>
                    </p>
                </div>

                <!-- Add Flag -->
                <div class="dgptm-add-flag">
                    <select id="dgptm-flag-type" class="dgptm-select">
                        <option value=""><?php _e('Flag auswählen...', 'dgptm-suite'); ?></option>
                        <option value="critical"><?php _e('Kritisch', 'dgptm-suite'); ?></option>
                        <option value="important"><?php _e('Wichtig', 'dgptm-suite'); ?></option>
                        <option value="testing"><?php _e('Im Test', 'dgptm-suite'); ?></option>
                        <option value="production"><?php _e('Produktiv', 'dgptm-suite'); ?></option>
                        <option value="development"><?php _e('In Entwicklung', 'dgptm-suite'); ?></option>
                        <option value="deprecated"><?php _e('Veraltet', 'dgptm-suite'); ?></option>
                        <option value="custom"><?php _e('Benutzerdefiniert', 'dgptm-suite'); ?></option>
                    </select>
                    <input type="text" id="dgptm-flag-label" class="dgptm-input"
                           placeholder="<?php _e('Optional: Eigenes Label', 'dgptm-suite'); ?>">
                    <button type="button" class="button button-secondary" id="dgptm-add-flag-btn">
                        <span class="dashicons dashicons-plus-alt2"></span>
                        <?php _e('Flag hinzufügen', 'dgptm-suite'); ?>
                    </button>
                </div>
            </div>

            <!-- Comments Section -->
            <div class="dgptm-metadata-section dgptm-comments-section">
                <h3><?php _e('Kommentar', 'dgptm-suite'); ?></h3>
                <textarea id="dgptm-module-comment" class="dgptm-textarea" rows="4"
                          placeholder="<?php _e('Notizen zu diesem Modul...', 'dgptm-suite'); ?>"></textarea>
                <button type="button" class="button button-secondary" id="dgptm-save-comment-btn">
                    <span class="dashicons dashicons-saved"></span>
                    <?php _e('Kommentar speichern', 'dgptm-suite'); ?>
                </button>
            </div>

            <!-- Version Switching Section -->
            <div class="dgptm-metadata-section dgptm-version-section">
                <h3><?php _e('Versionsumschaltung', 'dgptm-suite'); ?></h3>

                <!-- Version Status -->
                <div id="dgptm-version-status" class="dgptm-version-status">
                    <!-- Wird per JavaScript gefüllt -->
                </div>

                <!-- Link Test Version -->
                <div class="dgptm-link-version" id="dgptm-link-version-ui" style="display:none;">
                    <p><?php _e('Verknüpfe dieses Modul mit einer Test-Version:', 'dgptm-suite'); ?></p>
                    <select id="dgptm-test-module-select" class="dgptm-select">
                        <option value=""><?php _e('Modul auswählen...', 'dgptm-suite'); ?></option>
                    </select>
                    <button type="button" class="button button-secondary" id="dgptm-link-version-btn">
                        <span class="dashicons dashicons-admin-links"></span>
                        <?php _e('Verknüpfen', 'dgptm-suite'); ?>
                    </button>
                </div>

                <!-- Switch Version Button -->
                <div class="dgptm-switch-version" id="dgptm-switch-version-ui" style="display:none;">
                    <button type="button" class="button button-primary" id="dgptm-switch-version-btn">
                        <span class="dashicons dashicons-update"></span>
                        <span id="dgptm-switch-version-text"></span>
                    </button>
                </div>
            </div>
        </div>

        <div class="dgptm-modal-footer">
            <button type="button" class="button button-primary dgptm-modal-close">
                <?php _e('Schließen', 'dgptm-suite'); ?>
            </button>
        </div>
    </div>
</div>

<style>
/* Modal Styles */
.dgptm-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 100000;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding-top: 50px;
}

.dgptm-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    z-index: 1;
}

.dgptm-modal-content {
    position: relative;
    background: #fff;
    max-width: 700px;
    width: 90%;
    border-radius: 8px;
    box-shadow: 0 5px 25px rgba(0, 0, 0, 0.3);
    max-height: 85vh;
    display: flex;
    flex-direction: column;
    z-index: 2;
}

.dgptm-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 25px;
    border-bottom: 1px solid #ddd;
}

.dgptm-modal-header h2 {
    margin: 0;
    font-size: 22px;
}

.dgptm-modal-close {
    background: none;
    border: none;
    font-size: 32px;
    line-height: 1;
    cursor: pointer;
    padding: 0;
    color: #666;
}

.dgptm-modal-close:hover {
    color: #000;
}

.dgptm-modal-body {
    padding: 25px;
    overflow-y: auto;
    flex: 1;
}

.dgptm-modal-footer {
    padding: 15px 25px;
    border-top: 1px solid #ddd;
    text-align: right;
}

.dgptm-metadata-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;
}

.dgptm-metadata-section:last-child {
    border-bottom: none;
}

.dgptm-metadata-section h3 {
    margin-top: 0;
    margin-bottom: 15px;
    font-size: 16px;
    font-weight: 600;
}

.dgptm-module-id {
    color: #666;
    font-size: 13px;
}

/* Flags */
.dgptm-current-flags {
    margin-bottom: 15px;
}

.dgptm-flag {
    display: inline-flex;
    align-items: center;
    padding: 5px 12px;
    margin: 0 8px 8px 0;
    border-radius: 15px;
    font-size: 12px;
    font-weight: 600;
    color: #fff;
    gap: 6px;
}

.dgptm-flag-remove {
    background: none;
    border: none;
    color: rgba(255, 255, 255, 0.8);
    cursor: pointer;
    padding: 0;
    line-height: 1;
    font-size: 16px;
}

.dgptm-flag-remove:hover {
    color: #fff;
}

.dgptm-flag-critical { background: #d63638; }
.dgptm-flag-important { background: #ffb900; }
.dgptm-flag-testing { background: #0073aa; }
.dgptm-flag-production { background: #826eb4; }
.dgptm-flag-dev { background: #46b450; }
.dgptm-flag-deprecated { background: #dc3232; }
.dgptm-flag-custom { background: #666; }

.dgptm-add-flag {
    display: flex;
    gap: 10px;
    align-items: center;
}

.dgptm-select {
    flex: 1;
    max-width: 200px;
}

.dgptm-input {
    flex: 1;
    max-width: 250px;
}

.dgptm-textarea {
    width: 100%;
    margin-bottom: 10px;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-family: inherit;
}

.dgptm-version-status {
    background: #f5f5f5;
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 15px;
}

.dgptm-version-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    margin-left: 8px;
}

.dgptm-version-badge.active-main {
    background: #46b450;
    color: #fff;
}

.dgptm-version-badge.active-test {
    background: #0073aa;
    color: #fff;
}

.dgptm-link-version,
.dgptm-switch-version {
    margin-top: 15px;
}

.dgptm-link-version select {
    margin-bottom: 10px;
    display: block;
    width: 100%;
}

.dgptm-no-flags {
    color: #666;
    font-style: italic;
    margin: 10px 0;
}
</style>
