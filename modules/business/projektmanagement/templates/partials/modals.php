<?php
/**
 * Partial: Shared modal templates
 * This file is included in other templates that need modals
 */
if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- Confirmation Modal -->
<div class="pm-modal pm-modal-confirm" id="pm-confirm-modal" style="display: none;">
    <div class="pm-modal-content pm-modal-small">
        <div class="pm-modal-header">
            <h3 id="pm-confirm-title">Bestaetigung</h3>
            <button type="button" class="pm-modal-close">&times;</button>
        </div>
        <div class="pm-modal-body">
            <p id="pm-confirm-message">Sind Sie sicher?</p>
            <div class="pm-form-actions">
                <button type="button" class="pm-btn pm-btn-secondary pm-modal-close">Abbrechen</button>
                <button type="button" class="pm-btn pm-btn-danger" id="pm-confirm-btn">Bestaetigen</button>
            </div>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div class="pm-loading-overlay" id="pm-loading" style="display: none;">
    <div class="pm-loading-spinner">
        <span class="dashicons dashicons-update pm-spin"></span>
        <p>Laden...</p>
    </div>
</div>
