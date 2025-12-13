<?php
/**
 * Template for Student Status Form
 *
 * Displays current student status and allows uploading new certificate
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="dgptm-student-status-container">
    <!-- Status Display -->
    <div class="dgptm-student-status-display" id="dgptm-student-status-display">
        <div class="status-loading">
            <p>Status wird geladen...</p>
        </div>
    </div>

    <!-- Button to open modal -->
    <button type="button" class="dgptm-student-btn" id="dgptm-student-open-modal">
        Studierendenstatus beantragen / erneuern
    </button>

    <!-- Modal -->
    <div id="dgptm-student-modal" class="dgptm-modal" style="display: none;">
        <div class="dgptm-modal-content">
            <span class="dgptm-close-modal">&times;</span>
            <h2>Studienbescheinigung einreichen</h2>

            <div class="dgptm-modal-body">
                <p>Laden Sie hier Ihre aktuelle Studienbescheinigung hoch, um den Studierendenstatus zu beantragen oder zu erneuern.</p>

                <form id="dgptm-student-upload-form">
                    <div class="form-group">
                        <label for="dgptm-valid-year">Gültig bis Beitragsjahr:</label>
                        <input
                            type="number"
                            id="dgptm-valid-year"
                            name="year"
                            placeholder="z.B. 2025"
                            min="2024"
                            max="2030"
                            required
                        />
                        <small>Das Jahr, bis zu dem die Bescheinigung gültig ist</small>
                    </div>

                    <div class="form-group">
                        <label for="dgptm-certificate-file">Studienbescheinigung (PDF, JPG, PNG, max. 2MB):</label>
                        <input
                            type="file"
                            id="dgptm-certificate-file"
                            name="certificate_file"
                            accept=".pdf,.jpg,.jpeg,.png"
                            required
                        />
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="dgptm-btn-primary" id="dgptm-upload-btn">
                            Hochladen &amp; Absenden
                        </button>
                        <button type="button" class="dgptm-btn-secondary dgptm-close-modal">
                            Abbrechen
                        </button>
                    </div>

                    <div id="dgptm-upload-response" class="upload-response"></div>
                </form>
            </div>
        </div>
    </div>
</div>
