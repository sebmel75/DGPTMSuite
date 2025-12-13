<?php
/**
 * Member Data Edit Form Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="dgptm-daten-bearbeiten-wrapper">
    <div id="loading-message" class="loading-message">
        <span class="spinner"></span>
        <p>Daten werden geladen...</p>
    </div>

    <form id="dgptm-daten-form" class="dgptm-form" style="display: none;">

        <!-- Persönliche Daten -->
        <fieldset class="form-section">
            <legend>Persönliche Daten</legend>

            <div class="form-row">
                <div class="form-field frm_sixth frm_first">
                    <label for="ansprache">Ansprache</label>
                    <select id="ansprache" name="ansprache">
                        <option value="Moin">Moin</option>
                        <option value="Servus">Servus</option>
                        <option value="Liebe">Liebe</option>
                        <option value="Liebe(r)">Liebe(r)</option>
                        <option value="Hallo">Hallo</option>
                        <option value="Sehr geehrte">Sehr geehrte</option>
                        <option value="Sehr geehrter">Sehr geehrter</option>
                        <option value="Lieber">Lieber</option>
                    </select>
                </div>

                <div class="form-field frm_sixth">
                    <label for="akad_titel">Akad. Titel</label>
                    <input type="text" id="akad_titel" name="akad_titel">
                </div>

                <div class="form-field frm_half">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" readonly disabled>
                    <small class="description">Für Namensänderungen kontaktieren Sie bitte die Geschäftsstelle.</small>
                </div>

                <div class="form-field frm_sixth">
                    <label for="titel_nach_name">Bezeichnung hinter dem Namen</label>
                    <input type="text" id="titel_nach_name" name="titel_nach_name" placeholder="z.B. ECCP, AfK ect.">
                </div>
            </div>
        </fieldset>

        <!-- E-Mail-Adressen -->
        <fieldset class="form-section">
            <legend>E-Mail-Adressen</legend>

            <!-- DGPTM Account Hinweis (wird per JS eingeblendet wenn DGPTMMail vorhanden) -->
            <div id="dgptm-account-notice" class="form-row" style="display: none;">
                <div class="form-field frm12">
                    <div class="info-box dgptm-account-info" style="background: #e7f3ff; border: 1px solid #0073aa; padding: 12px 15px; border-radius: 4px; margin-bottom: 15px;">
                        <span id="dgptm-account-text"></span>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-field frm6 frm_first">
                    <label for="mail1">Private Mailadresse <span class="required">*</span></label>
                    <input type="email" id="mail1" name="mail1" required>
                    <small class="description">Mit dieser Mailadresse können Sie sich einloggen. Die DGPTM verwendet primär diese Mailadresse zur Kommunikation mit Ihnen.</small>
                </div>
            </div>

            <div class="form-row">
                <div class="form-field frm_half">
                    <label for="mail2">Zweite (dienstliche) Mailadresse</label>
                    <input type="email" id="mail2" name="mail2">
                </div>

                <div class="form-field frm_half">
                    <label for="mail3">Optionale Mailadresse</label>
                    <input type="email" id="mail3" name="mail3">
                </div>
            </div>

            <div class="form-row">
                <div class="form-field frm12">
                    <small class="description info-box">Diese Adressen können NICHT zum Login verwendet werden, sind aber wichtig zur Wiedererkennung. Bitte verwenden Sie bei Veranstaltungen und der Kommunikation mit der DGPTM ausschließlich eine dieser Adressen.</small>
                </div>
            </div>
        </fieldset>

        <!-- Adresse -->
        <fieldset class="form-section">
            <legend>Adresse</legend>

            <div class="form-row">
                <div class="form-field frm_two_thirds frm_first">
                    <label for="strasse">Straße <span class="required">*</span></label>
                    <input type="text" id="strasse" name="strasse" required>
                </div>

                <div class="form-field frm_third">
                    <label for="adresszusatz">Adresszusatz</label>
                    <input type="text" id="adresszusatz" name="adresszusatz">
                </div>
            </div>

            <div class="form-row">
                <div class="form-field frm4 frm_first">
                    <label for="plz">Postleitzahl <span class="required">*</span></label>
                    <input type="text" id="plz" name="plz" required>
                </div>

                <div class="form-field frm4">
                    <label for="ort">Ort <span class="required">*</span></label>
                    <input type="text" id="ort" name="ort" required>
                </div>

                <div class="form-field frm4">
                    <label for="land">Land</label>
                    <select id="land" name="land">
                        <option value="Deutschland">Deutschland</option>
                        <option value="Schweiz">Schweiz</option>
                        <option value="Österreich">Österreich</option>
                        <option value="Luxemburg">Luxemburg</option>
                        <option value="Lichtenstein">Lichtenstein</option>
                        <option value="Frankreich">Frankreich</option>
                        <option value="Dänemark">Dänemark</option>
                        <option value="Niederlande">Niederlande</option>
                        <option value="Belgien">Belgien</option>
                        <option value="Tschechien">Tschechien</option>
                        <option value="Polen">Polen</option>
                    </select>
                </div>
            </div>
        </fieldset>

        <!-- Telefonnummern -->
        <fieldset class="form-section">
            <legend>Telefonnummern</legend>

            <div class="form-row">
                <div class="form-field frm4 frm_first">
                    <label for="telefon">Telefon</label>
                    <input type="tel" id="telefon" name="telefon">
                </div>

                <div class="form-field frm4">
                    <label for="mobil">Mobil</label>
                    <input type="tel" id="mobil" name="mobil">
                </div>

                <div class="form-field frm4">
                    <label for="diensttelefon">Diensttelefon</label>
                    <input type="tel" id="diensttelefon" name="diensttelefon">
                </div>
            </div>
        </fieldset>

        <!-- Arbeitgeber -->
        <fieldset class="form-section">
            <legend>Arbeitgeber</legend>

            <div class="form-row" id="employer_current_row">
                <div class="form-field frm12 frm_first">
                    <label>Aktueller Arbeitgeber</label>
                    <div id="employer_current_display" style="padding: 10px; background: #f0f0f0; border: 1px solid #ddd; border-radius: 4px;">
                        <span id="employer_current_name" style="font-weight: 500;">Nicht zugeordnet</span>
                        <button type="button" id="employer_change_btn" class="btn-secondary" style="float: right; padding: 5px 15px; background: #0073aa; color: white; border: none; border-radius: 3px; cursor: pointer;">Ändern</button>
                    </div>
                </div>
            </div>

            <div id="employer_search_section" style="display: none;">
                <div class="form-row">
                    <div class="form-field frm12">
                        <label for="employer_search">Arbeitgeber suchen</label>
                        <div style="display: flex; gap: 10px;">
                            <input type="text" id="employer_search" placeholder="Name des Arbeitgebers eingeben (mind. 2 Zeichen)" style="flex: 1;">
                            <button type="button" id="employer_search_btn" class="btn-secondary" style="padding: 10px 20px; background: #0073aa; color: white; border: none; border-radius: 4px; cursor: pointer; white-space: nowrap;">Suchen</button>
                        </div>
                    </div>
                </div>

                <div class="form-row" id="employer_results_row" style="display: none;">
                    <div class="form-field frm12">
                        <label>Suchergebnisse</label>
                        <div id="employer_results" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; background: white;">
                            <!-- Results will be inserted here -->
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-field frm12">
                        <label class="toggle-label" style="background: #fff; border: 1px solid #ddd;">
                            <input type="checkbox" id="employer_not_in_list" name="employer_not_in_list">
                            <span class="toggle-slider"></span>
                            Arbeitgeber ist nicht in der Liste
                        </label>
                    </div>
                </div>

                <div class="form-row" id="employer_manual_row" style="display: none;">
                    <div class="form-field frm12">
                        <label for="employer_manual">Arbeitgeber (manuell eingeben)</label>
                        <input type="text" id="employer_manual" name="employer_manual" placeholder="Name des Arbeitgebers">
                    </div>
                </div>
            </div>

            <!-- Hidden fields to store selected employer -->
            <input type="hidden" id="employer" name="employer">
            <input type="hidden" id="employer_id" name="employer_id">

            <div class="form-row" id="temporary_work_row" style="display: none;">
                <div class="form-field frm12">
                    <label for="temporary_work">Ausgeliehen an Klinik:</label>
                    <select id="temporary_work" name="temporary_work">
                        <option value="">Keine Ausleihe</option>
                    </select>
                </div>
            </div>
        </fieldset>

        <!-- Zahlung des Mitgliedsbeitrages -->
        <fieldset class="form-section" id="payment-section">
            <legend>Zahlung des Mitgliedsbeitrages</legend>

            <div class="form-row">
                <div class="form-field frm12 frm_first">
                    <?php
                    // GoCardless Shortcode einbinden (wenn Modul aktiv)
                    if (shortcode_exists('gcl_formidable')) {
                        echo do_shortcode('[gcl_formidable]');
                    } else {
                        echo '<p style="color: #666;">Zahlungsinformationen werden geladen...</p>';
                    }
                    ?>
                </div>
            </div>

            <!-- Bankdaten ändern Option -->
            <div class="form-row">
                <div class="form-field frm12">
                    <label class="toggle-label" style="background: #fff3cd; border: 1px solid #ffc107;">
                        <input type="checkbox" id="bank_change_requested" name="bank_change_requested">
                        <span class="toggle-slider"></span>
                        Ich möchte meine Bankdaten ändern
                    </label>
                </div>
            </div>

            <div id="bank_change_section" style="display: none;">
                <div class="form-row">
                    <div class="form-field frm_half frm_first">
                        <label for="bank_vorname">Vorname (Kontoinhaber) <span class="required">*</span></label>
                        <input type="text" id="bank_vorname" name="bank_vorname">
                    </div>
                    <div class="form-field frm_half">
                        <label for="bank_nachname">Nachname (Kontoinhaber) <span class="required">*</span></label>
                        <input type="text" id="bank_nachname" name="bank_nachname">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-field frm12">
                        <label for="bank_iban">IBAN <span class="required">*</span></label>
                        <input type="text" id="bank_iban" name="bank_iban" placeholder="DE00 0000 0000 0000 0000 00" maxlength="34">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-field frm12">
                        <div class="info-box" style="background: #fff3cd; border-color: #ffc107;">
                            <strong>Hinweis:</strong> Nach dem Absenden wird Ihr aktuelles Lastschriftmandat deaktiviert.
                            Die Geschäftsstelle wird informiert und richtet ein neues Mandat mit den neuen Bankdaten ein.
                        </div>
                    </div>
                </div>
            </div>
        </fieldset>

        <!-- Zeitschrift DIE PERFUSIOLOGIE -->
        <fieldset class="form-section">
            <legend>Wie möchten Sie die Zeitschrift DIE PERFUSIOLOGIE ab der kommenden Ausgabe erhalten?</legend>

            <div class="form-row">
                <div class="form-field frm6 frm_first">
                    <label class="toggle-label">
                        <input type="checkbox" id="journal_post" name="journal_post" value="true">
                        <span class="toggle-slider"></span>
                        Ich möchte DIE PERFUSIOLOGIE per Post als gedrucktes Journal erhalten
                    </label>
                </div>

                <div class="form-field frm6">
                    <label class="toggle-label">
                        <input type="checkbox" id="journal_mail" name="journal_mail" value="true">
                        <span class="toggle-slider"></span>
                        Ich möchte DIE PERFUSIOLOGIE per Mail erhalten
                    </label>
                </div>
            </div>
        </fieldset>

        <!-- Submit Button -->
        <div class="form-actions">
            <button type="submit" class="btn-submit" id="submit-btn">
                Daten ändern
            </button>
        </div>

        <div id="form-messages" class="form-messages"></div>
    </form>

    <div id="success-message" class="success-message" style="display: none;">
        <div class="success-icon">✓</div>
        <h3>Daten erfolgreich gespeichert!</h3>
        <p>Sie werden weitergeleitet...</p>
    </div>
</div>
