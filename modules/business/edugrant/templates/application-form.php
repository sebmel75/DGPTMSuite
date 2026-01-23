<?php
/**
 * Template: EduGrant Application/Submission Form
 * Shortcode: [edugrant_antragsformular]
 *
 * URL Parameters:
 * - event_id: Zoho Event ID for new applications
 * - eduid: Zoho EduGrant ID for document submission (from email link)
 * - edugrant_code: Alternative to eduid (EduGrant number like EDUGRANT-2023-8)
 *
 * Supports both logged-in members and guests (non-logged-in users)
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

// Check if user is logged in
$is_logged_in = is_user_logged_in();

// Get current user info (if logged in)
$current_user = wp_get_current_user();
$zoho_contact_id = $is_logged_in ? get_user_meta(get_current_user_id(), 'zoho_id', true) : '';

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

        <!-- Login hint for guests -->
        <?php if (!$is_logged_in): ?>
            <div class="edugrant-info-box edugrant-login-hint">
                <p><strong>Mitglieder:</strong> Bitte loggen Sie sich vorher ein, um das Formular automatisch auszufüllen.</p>
                <a href="<?php echo wp_login_url(add_query_arg([])); ?>" class="button edugrant-login-btn">
                    <span class="dashicons dashicons-admin-users"></span>
                    Jetzt einloggen
                </a>
                <p style="margin-top: 10px; margin-bottom: 0;"><small>Kein Mitglied? Füllen Sie das Formular als Gast aus.</small></p>
            </div>
        <?php endif; ?>

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
            <form id="edugrant-application-form" class="edugrant-form" data-event-id="<?php echo esc_attr($event_id); ?>" data-contact-id="<?php echo esc_attr($zoho_contact_id); ?>" data-logged-in="<?php echo $is_logged_in ? '1' : '0'; ?>">
                <?php wp_nonce_field('dgptm_edugrant_nonce', 'edugrant_nonce'); ?>

                <?php if ($is_logged_in): ?>
                    <!-- Antragsteller-Info (kompakt) - nur für eingeloggte Benutzer -->
                    <div class="edugrant-applicant-info">
                        <span class="dashicons dashicons-admin-users"></span>
                        <strong><?php echo esc_html($current_user->display_name); ?></strong>
                        <span class="applicant-email">(<?php echo esc_html($current_user->user_email); ?>)</span>
                    </div>
                <?php else: ?>
                    <!-- SCHRITT 1: E-Mail-Prüfung -->
                    <fieldset class="edugrant-fieldset edugrant-guest-step" id="guest-step-email">
                        <legend>E-Mail-Adresse</legend>

                        <div class="edugrant-notice" style="margin-bottom: 15px;">
                            <span class="dashicons dashicons-info"></span>
                            Bitte die gleiche E-Mail-Adresse wie bei der Ticketbuchung verwenden!
                        </div>

                        <div class="form-row">
                            <label for="guest_email">E-Mail-Adresse *</label>
                            <input type="email" id="guest_email" name="guest_email" required>
                        </div>

                        <div class="form-row">
                            <button type="button" id="check-email-btn" class="button edugrant-check-btn">
                                <span class="dashicons dashicons-search"></span>
                                E-Mail prüfen
                            </button>
                        </div>

                        <!-- Status-Anzeige nach E-Mail-Prüfung -->
                        <div id="email-check-result" class="email-check-result" style="display: none;"></div>
                    </fieldset>

                    <!-- SCHRITT 2: Kontaktdaten (nur für externe Veranstaltungen oder neue Kontakte) -->
                    <fieldset class="edugrant-fieldset edugrant-guest-step" id="guest-step-contact" style="display: none;">
                        <legend>Kontaktdaten vervollständigen</legend>

                        <div class="form-row form-row-half">
                            <label for="guest_vorname">Vorname *</label>
                            <input type="text" id="guest_vorname" name="guest_vorname">
                        </div>

                        <div class="form-row form-row-half">
                            <label for="guest_nachname">Nachname *</label>
                            <input type="text" id="guest_nachname" name="guest_nachname">
                        </div>

                        <div class="form-row">
                            <label for="guest_strasse">Straße und Hausnummer *</label>
                            <input type="text" id="guest_strasse" name="guest_strasse">
                        </div>

                        <div class="form-row form-row-third">
                            <label for="guest_plz">PLZ *</label>
                            <input type="text" id="guest_plz" name="guest_plz" maxlength="10">
                        </div>

                        <div class="form-row form-row-twothird">
                            <label for="guest_ort">Ort *</label>
                            <input type="text" id="guest_ort" name="guest_ort">
                        </div>
                    </fieldset>

                    <!-- SCHRITT 3: Berechtigung (für alle Nichtmitglieder / nicht eingeloggte Nutzer) -->
                    <fieldset class="edugrant-fieldset edugrant-guest-step" id="guest-step-berechtigung" style="display: none;">
                        <legend>Berechtigung nachweisen</legend>

                        <div class="form-row">
                            <label for="guest_berechtigung">Ich bin antragsberechtigt, weil: *</label>
                            <select id="guest_berechtigung" name="guest_berechtigung">
                                <option value="">-- Bitte wählen --</option>
                                <option value="perfusionist">Ich bin PerfusionistIn</option>
                                <option value="student">Ich bin StudentIn eines fachbezogenen Studienganges</option>
                                <option value="sonstiges">Ich bin aus einem anderen Grund berechtigt</option>
                            </select>
                        </div>

                        <div class="form-row" id="guest_berechtigung_sonstiges_row" style="display: none;">
                            <label for="guest_berechtigung_text">Bitte erläutern Sie Ihre Berechtigung: *</label>
                            <input type="text" id="guest_berechtigung_text" name="guest_berechtigung_text">
                        </div>

                        <div class="form-row">
                            <label for="guest_nachweis">Nachweis hochladen (PDF, JPG, PNG) *</label>
                            <input type="file" id="guest_nachweis" name="guest_nachweis" accept=".pdf,.jpg,.jpeg,.png">
                            <small>Z.B. Arbeitsvertrag, Studentenausweis, Zertifikat</small>
                        </div>
                    </fieldset>

                    <!-- Versteckte Felder für Kontakt-ID und Status -->
                    <input type="hidden" id="guest_contact_id" name="guest_contact_id" value="">
                    <input type="hidden" id="guest_contact_found" name="guest_contact_found" value="0">
                    <input type="hidden" id="guest_has_ticket" name="guest_has_ticket" value="0">
                    <input type="hidden" id="guest_is_member" name="guest_is_member" value="0">
                <?php endif; ?>

                <div class="edugrant-event-info" id="event-info-container">
                    <div class="loading-indicator">
                        <span class="dashicons dashicons-update spin"></span>
                        Lade Veranstaltungsdaten...
                    </div>
                </div>

                <?php if ($is_logged_in): ?>
                <!-- Ticket-Prüfung für interne Veranstaltungen (nur für eingeloggte Benutzer) -->
                <div id="ticket-status-container" class="edugrant-ticket-status" style="display: none;">
                    <div class="loading-indicator">
                        <span class="dashicons dashicons-update spin"></span>
                        Prüfe Ticketstatus...
                    </div>
                </div>
                <?php endif; ?>

                <input type="hidden" name="event_id" value="<?php echo esc_attr($event_id); ?>">
                <input type="hidden" name="is_guest" value="<?php echo $is_logged_in ? '0' : '1'; ?>">

                <div class="edugrant-terms" style="display: none;">
                    <p>Mit dem Absenden dieses Antrags bestätige ich:</p>
                    <ul>
                        <li>Ich erfülle die Förderbedingungen.</li>
                        <li>Ich nehme an der ausgewählten Veranstaltung teil.</li>
                        <li>Ich werde die erforderlichen Nachweise nach der Veranstaltung einreichen.</li>
                    </ul>

                    <label class="checkbox-label">
                        <input type="checkbox" id="travel_policy_accepted" name="travel_policy_accepted" required>
                        Ich erkenne die <a href="https://dgptm.de/download/17887/" target="_blank">Reisekostenrichtlinie</a> an.
                    </label>

                    <label class="checkbox-label">
                        <input type="checkbox" id="privacy_accepted" name="privacy_accepted" required>
                        Ich stimme der <a href="https://dgptm.de/datenschutz" target="_blank">Datenschutzerklärung</a> und der Verarbeitung meiner Daten zu.
                    </label>

                    <label class="checkbox-label">
                        <input type="checkbox" id="terms_accepted" name="terms_accepted" required>
                        Ich habe die Bedingungen gelesen und akzeptiere sie.
                    </label>
                </div>

                <div class="form-actions">
                    <button type="submit" class="button edugrant-submit-btn" id="submit-application-btn" style="display: none;">
                        <span class="dashicons dashicons-yes"></span>
                        EduGrant beantragen
                    </button>
                </div>
            </form>
        <?php endif; ?>
    <?php endif; ?>

    <div id="edugrant-message" class="edugrant-message" style="display: none;"></div>
</div>
