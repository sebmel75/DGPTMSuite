<?php
/**
 * Template: EduGrant Application/Submission Form
 * Shortcode: [edugrant_antragsformular]
 *
 * URL Parameters:
 * - event_id: Zoho Event ID for new applications
 * - eduid: Zoho EduGrant ID for document submission (from email link)
 * - edugrant_code: Alternative to eduid (EduGrant number like EDUGRANT-2023-8)
 */

if (!defined('ABSPATH')) {
    exit;
}

$event_id = $atts['event_id'] ?? '';
$edugrant_code = $atts['edugrant_code'] ?? '';

// Check for eduid parameter (Zoho EduGrant Record ID from email link)
$eduid = isset($_GET['eduid']) ? sanitize_text_field($_GET['eduid']) : '';

// Determine mode: new application vs document submission
$is_document_submission = !empty($eduid) || !empty($edugrant_code);

// Get current user info
$current_user = wp_get_current_user();
$zoho_contact_id = get_user_meta(get_current_user_id(), 'zoho_id', true);

// If we have eduid, fetch the EduGrant details
$edugrant_data = null;
$event_data = null;

if ($is_document_submission && !empty($eduid)) {
    // This would fetch the specific EduGrant record
    // For now, we'll let JavaScript handle the data fetching
}
?>

<div class="edugrant-form-container">
    <?php if ($is_document_submission): ?>
        <!-- Document Submission Mode -->
        <h3 class="edugrant-form-title">
            <span class="dashicons dashicons-upload"></span>
            EduGrant Unterlagen einreichen
        </h3>

        <div class="edugrant-info-box">
            <p>
                Bitte laden Sie hier Ihre Nachweise für den EduGrant hoch.
                Die folgenden Unterlagen werden benötigt:
            </p>
            <ul>
                <li id="doc-teilnahme" style="display: none;"><strong>Teilnahmebestätigung / Zertifikat</strong> (erforderlich bei externen Veranstaltungen)</li>
                <li>Hotelrechnung (mit MwSt.-Ausweis)</li>
                <li>Fahrkarten / Tankbelege</li>
                <li>Kongressrechnung / Teilnahmegebühren</li>
            </ul>
        </div>

        <form id="edugrant-document-form" class="edugrant-form" data-eduid="<?php echo esc_attr($eduid); ?>" data-code="<?php echo esc_attr($edugrant_code); ?>">
            <?php wp_nonce_field('dgptm_edugrant_nonce', 'edugrant_nonce'); ?>

            <div class="edugrant-grant-info" id="grant-info-container">
                <div class="loading-indicator">
                    <span class="dashicons dashicons-update spin"></span>
                    Lade EduGrant-Daten...
                </div>
            </div>

            <fieldset class="edugrant-fieldset">
                <legend>Kostenaufstellung</legend>

                <div class="form-row">
                    <label for="unterkunft">Unterkunftskosten (&euro;)</label>
                    <input type="number" id="unterkunft" name="unterkunft" step="0.01" min="0" value="0">
                </div>

                <div class="form-row">
                    <label for="fahrtkosten">Fahrtkosten (&euro;)</label>
                    <input type="number" id="fahrtkosten" name="fahrtkosten" step="0.01" min="0" value="0">
                </div>

                <div class="form-row">
                    <label for="kilometer">Gefahrene Kilometer (falls PKW)</label>
                    <input type="number" id="kilometer" name="kilometer" min="0" value="0">
                    <small>0,20 &euro; pro Kilometer werden erstattet</small>
                </div>

                <div class="form-row checkbox-row">
                    <label>
                        <input type="checkbox" id="hin_rueck" name="hin_rueck" value="1">
                        Hin- und Rückfahrt mit PKW
                    </label>
                </div>

                <div class="form-row">
                    <label for="teilnahmegebuehren">Teilnahmegebühren (&euro;)</label>
                    <input type="number" id="teilnahmegebuehren" name="teilnahmegebuehren" step="0.01" min="0" value="0">
                </div>

                <div class="form-row total-row">
                    <label>Summe:</label>
                    <span id="total-amount" class="total-amount">0,00 &euro;</span>
                </div>
            </fieldset>

            <fieldset class="edugrant-fieldset">
                <legend>Bankverbindung</legend>

                <div class="form-row">
                    <label for="kontoinhaber">Kontoinhaber</label>
                    <input type="text" id="kontoinhaber" name="kontoinhaber"
                           value="<?php echo esc_attr($current_user->display_name); ?>" required>
                </div>

                <div class="form-row">
                    <label for="iban">IBAN</label>
                    <input type="text" id="iban" name="iban" pattern="[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}"
                           placeholder="DE00 0000 0000 0000 0000 00" required>
                </div>
            </fieldset>

            <fieldset class="edugrant-fieldset" id="fieldset-teilnahmebescheinigung" style="display: none;">
                <legend>Teilnahmebescheinigung (externe Veranstaltung)</legend>
                <div class="edugrant-notice" style="margin-bottom: 15px;">
                    <span class="dashicons dashicons-info"></span>
                    Bei externen Veranstaltungen ist eine Teilnahmebescheinigung oder ein Zertifikat erforderlich.
                </div>
                <div class="form-row">
                    <label for="teilnahmebescheinigung">Teilnahmebescheinigung / Zertifikat (PDF, JPG, PNG) *</label>
                    <input type="file" id="teilnahmebescheinigung" name="teilnahmebescheinigung"
                           accept=".pdf,.jpg,.jpeg,.png">
                </div>
            </fieldset>

            <fieldset class="edugrant-fieldset">
                <legend>Nachweise hochladen</legend>

                <div class="form-row">
                    <label for="belege">Belege und Nachweise (PDF, JPG, PNG)</label>
                    <input type="file" id="belege" name="belege[]" multiple
                           accept=".pdf,.jpg,.jpeg,.png">
                    <small>Mehrere Dateien können ausgewählt werden</small>
                </div>

                <div id="file-preview" class="file-preview"></div>
            </fieldset>

            <div class="form-actions">
                <button type="submit" class="button edugrant-submit-btn">
                    <span class="dashicons dashicons-yes"></span>
                    Abrechnung einreichen
                </button>
            </div>
        </form>

    <?php else: ?>
        <!-- New Application Mode -->
        <h3 class="edugrant-form-title">
            <span class="dashicons dashicons-awards"></span>
            EduGrant beantragen
        </h3>

        <?php if (empty($event_id)): ?>
            <div class="edugrant-event-select">
                <p>Bitte wählen Sie eine Veranstaltung aus:</p>
                <div id="event-select-container">
                    <div class="loading-indicator">
                        <span class="dashicons dashicons-update spin"></span>
                        Lade verfügbare Veranstaltungen...
                    </div>
                </div>
            </div>
        <?php else: ?>
            <form id="edugrant-application-form" class="edugrant-form" data-event-id="<?php echo esc_attr($event_id); ?>" data-contact-id="<?php echo esc_attr($zoho_contact_id); ?>">
                <?php wp_nonce_field('dgptm_edugrant_nonce', 'edugrant_nonce'); ?>

                <!-- Antragsteller-Info (kompakt) -->
                <div class="edugrant-applicant-info">
                    <span class="dashicons dashicons-admin-users"></span>
                    <strong><?php echo esc_html($current_user->display_name); ?></strong>
                    <span class="applicant-email">(<?php echo esc_html($current_user->user_email); ?>)</span>
                </div>

                <div class="edugrant-event-info" id="event-info-container">
                    <div class="loading-indicator">
                        <span class="dashicons dashicons-update spin"></span>
                        Lade Veranstaltungsdaten...
                    </div>
                </div>

                <!-- Ticket-Prüfung für interne Veranstaltungen -->
                <div id="ticket-status-container" class="edugrant-ticket-status" style="display: none;">
                    <div class="loading-indicator">
                        <span class="dashicons dashicons-update spin"></span>
                        Prüfe Ticketstatus...
                    </div>
                </div>

                <input type="hidden" name="event_id" value="<?php echo esc_attr($event_id); ?>">

                <div class="edugrant-terms">
                    <p>Mit dem Absenden dieses Antrags bestätige ich:</p>
                    <ul>
                        <li>Ich bin ordentliches Mitglied der DGPTM.</li>
                        <li>Ich nehme an der ausgewählten Veranstaltung teil.</li>
                        <li>Ich werde die erforderlichen Nachweise nach der Veranstaltung einreichen.</li>
                    </ul>

                    <label class="checkbox-label">
                        <input type="checkbox" id="terms_accepted" name="terms_accepted" required>
                        Ich habe die Bedingungen gelesen und akzeptiere sie.
                    </label>
                </div>

                <div class="form-actions">
                    <button type="submit" class="button edugrant-submit-btn" id="submit-application-btn">
                        <span class="dashicons dashicons-yes"></span>
                        EduGrant beantragen
                    </button>
                </div>
            </form>
        <?php endif; ?>
    <?php endif; ?>

    <div id="edugrant-message" class="edugrant-message" style="display: none;"></div>
</div>
