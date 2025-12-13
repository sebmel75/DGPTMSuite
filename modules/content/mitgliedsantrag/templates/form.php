<?php
/**
 * Membership Application Form Template
 *
 * Multi-step form with validation, guarantor verification, and student certificate upload
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="dgptm-mitgliedsantrag-wrapper">
    <div class="dgptm-mitgliedsantrag-progress">
        <div class="progress-step active" data-step="1">
            <span class="step-number">1</span>
            <span class="step-title">Basisdaten</span>
        </div>
        <div class="progress-step" data-step="2">
            <span class="step-number">2</span>
            <span class="step-title">Adresse</span>
        </div>
        <div class="progress-step" data-step="3">
            <span class="step-number">3</span>
            <span class="step-title">Studienbescheinigung</span>
        </div>
        <div class="progress-step" data-step="4">
            <span class="step-number">4</span>
            <span class="step-title">Bürgen</span>
        </div>
        <div class="progress-step" data-step="5">
            <span class="step-number">5</span>
            <span class="step-title">Bestätigung</span>
        </div>
    </div>

    <form id="dgptm-mitgliedsantrag-form" class="dgptm-mitgliedsantrag-form" enctype="multipart/form-data">

        <!-- Step 1: Basisdaten -->
        <div class="form-step active" data-step="1">
            <h3>Basisdaten</h3>

            <div class="form-row">
                <div class="form-group frm6 frm_first">
                    <label for="ansprache">Ansprache *</label>
                    <select id="ansprache" name="ansprache" required>
                        <option value="">Bitte wählen...</option>
                        <option value="Liebe(r)">Liebe(r)</option>
                        <option value="Hallo">Hallo</option>
                        <option value="Liebe">Liebe</option>
                        <option value="Lieber">Lieber</option>
                        <option value="Liebe Frau">Liebe Frau</option>
                        <option value="Lieber Herr">Lieber Herr</option>
                        <option value="Moin">Moin</option>
                        <option value="Sehr geehrte">Sehr geehrte</option>
                        <option value="Sehr geehrte Frau">Sehr geehrte Frau</option>
                        <option value="Sehr geehrte Frau Dr.">Sehr geehrte Frau Dr.</option>
                        <option value="Sehr geehrte Frau Prof. Dr.">Sehr geehrte Frau Prof. Dr.</option>
                        <option value="Sehr geehrter Herr">Sehr geehrter Herr</option>
                        <option value="Servus">Servus</option>
                    </select>
                </div>

                <div class="form-group frm6">
                    <label for="geburtsdatum">Geburtsdatum *</label>
                    <input type="date" id="geburtsdatum" name="geburtsdatum" max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group frm_sixth frm_first">
                    <label for="akad_titel">Akad. Titel</label>
                    <input type="text" id="akad_titel" name="akad_titel" placeholder="z.B. Dr.">
                </div>

                <div class="form-group frm_two_thirds">
                    <label for="vorname">Vorname *</label>
                    <input type="text" id="vorname" name="vorname" required placeholder="Vorname">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group frm_two_thirds">
                    <label for="nachname">Nachname *</label>
                    <input type="text" id="nachname" name="nachname" required placeholder="Nachname">
                </div>

                <div class="form-group frm_sixth">
                    <label for="titel_nachgestellt">Titel</label>
                    <input type="text" id="titel_nachgestellt" name="titel_nachgestellt" placeholder="z.B. B.Sc., M.Sc., ECCP">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group frm12">
                    <label for="mitgliedsart">Mitgliedsart *</label>
                    <div class="membership-select">
                        <span class="membership-prepend">Ich möchte zum nächstmöglichen Zeitpunkt</span>
                        <select id="mitgliedsart" name="mitgliedsart" required>
                            <option value="">Bitte auswählen...</option>
                            <option value="ordentliches">ordentliches</option>
                            <option value="außerordentliches">außerordentliches</option>
                            <option value="förderndes">förderndes</option>
                        </select>
                        <span class="membership-append">Mitglied der DGPTM werden.</span>
                    </div>
                </div>
            </div>

            <!-- Qualifikationsnachweis-Sektion (nur für ordentliche Mitglieder) -->
            <div id="qualification-section" style="display: none;">
                <div class="form-row">
                    <div class="form-group frm12">
                        <label>Haben Sie eine der folgenden Qualifikationen?</label>
                        <div class="info-box">
                            <p><strong>Anerkannte Qualifikationen gemäß § 4 der Satzung:</strong></p>
                            <ul>
                                <li>European Certified Clinical Perfusionist (ECCP)</li>
                                <li>Berliner Gesetz (Perfusionist nach § 8 GKwG Berlin)</li>
                                <li>Äquivalente Qualifikationen</li>
                            </ul>
                        </div>

                        <div class="radio-group">
                            <label class="radio-label">
                                <input type="radio" name="hat_qualifikation" value="ja">
                                <span>Ja, ich habe eine der genannten Qualifikationen und kann diese nachweisen</span>
                            </label>
                            <label class="radio-label">
                                <input type="radio" name="hat_qualifikation" value="nein">
                                <span>Nein, ich habe keine dieser Qualifikationen oder bin Student:in</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Upload-Bereich für Qualifikationsnachweis -->
                <div id="qualification-upload" style="display: none;">
                    <div class="form-row">
                        <div class="form-group frm6 frm_first">
                            <label for="qualifikation_typ">Art der Qualifikation *</label>
                            <select id="qualifikation_typ" name="qualifikation_typ">
                                <option value="">Bitte wählen...</option>
                                <option value="ECCP">European Certified Clinical Perfusionist (ECCP)</option>
                                <option value="Berliner_Gesetz">Berliner Gesetz (§ 8 GKwG)</option>
                                <option value="Aequivalent">Äquivalente Qualifikation</option>
                            </select>
                        </div>

                        <div class="form-group frm6">
                            <label for="qualifikation_nachweis">Nachweis hochladen *</label>
                            <input type="file" id="qualifikation_nachweis" name="qualifikation_nachweis" accept=".jpg,.jpeg,.png,.pdf">
                            <small>Erlaubte Formate: JPG, PNG, PDF (max. 5 MB)</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-navigation">
                <button type="button" class="btn-next">Weiter</button>
            </div>
        </div>

        <!-- Step 2: Adresse -->
        <div class="form-step" data-step="2">
            <h3>Private Adresse</h3>
            <p class="form-notice"><strong>Wichtig:</strong> Bitte keinesfalls die dienstliche Adresse angeben. Sie treten immer als Privatperson in die Gesellschaft ein.</p>

            <div class="form-row">
                <div class="form-group frm12">
                    <label for="strasse">Straße und Hausnummer *</label>
                    <input type="text" id="strasse" name="strasse" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group frm12">
                    <label for="zusatz">Adresszusatz</label>
                    <input type="text" id="zusatz" name="zusatz" placeholder="z.B. Wohnung 3">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group frm4 frm_first">
                    <label for="plz">Postleitzahl *</label>
                    <input type="text" id="plz" name="plz" required pattern="\d{5}" placeholder="12345">
                </div>

                <div class="form-group frm4">
                    <label for="stadt">Stadt *</label>
                    <input type="text" id="stadt" name="stadt" required>
                </div>

                <div class="form-group frm4">
                    <label for="bundesland">Bundesland</label>
                    <input type="text" id="bundesland" name="bundesland">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group frm4 frm_first">
                    <label for="land">Land *</label>
                    <input type="text" id="land" name="land" value="Deutschland" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group frm4 frm_first">
                    <label for="email1">Private E-Mail-Adresse * <span class="validation-icon"></span></label>
                    <input type="email" id="email1" name="email1" required>
                    <small>Mit dieser Adresse können Sie sich später in den Mitgliederbereich einloggen. <strong>Erforderlich.</strong></small>
                </div>

                <div class="form-group frm4">
                    <label for="email2">2. E-Mail-Adresse (z.B. dienstlich) <span class="validation-icon"></span></label>
                    <input type="email" id="email2" name="email2">
                </div>

                <div class="form-group frm4">
                    <label for="email3">3. E-Mail-Adresse <span class="validation-icon"></span></label>
                    <input type="email" id="email3" name="email3">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group frm6 frm_first">
                    <label for="telefon1">Telefonnummer</label>
                    <input type="tel" id="telefon1" name="telefon1" placeholder="Wie sind Sie am besten zu erreichen?">
                </div>

                <div class="form-group frm6">
                    <label for="telefon2">2. Telefonnummer</label>
                    <input type="tel" id="telefon2" name="telefon2" placeholder="z.B. dienstlich">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group frm_three_fourths frm_first">
                    <label for="arbeitgeber">Hochschule / Klinik / Arbeitgeber</label>
                    <input type="text" id="arbeitgeber" name="arbeitgeber">
                </div>

                <div class="form-group frm_fourth">
                    <label for="ist_student">Ich bin Student/in</label>
                    <div class="toggle-switch">
                        <input type="checkbox" id="ist_student" name="ist_student" value="1">
                        <label for="ist_student" class="toggle-label">
                            <span class="toggle-inner"></span>
                            <span class="toggle-switch-slider"></span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="form-navigation">
                <button type="button" class="btn-prev">Zurück</button>
                <button type="button" class="btn-next">Weiter</button>
            </div>
        </div>

        <!-- Step 3: Studienbescheinigung (nur für studentische ordentliche Mitglieder) -->
        <div class="form-step" data-step="3">
            <div id="student-fields" style="display: none;">
                <h3>Studienbescheinigung</h3>

                <div class="info-box">
                    <p><strong>Studentische ordentliche Mitgliedschaft</strong></p>
                    <p>Als Student/in eines relevanten Studiengangs (Technische Medizin/Perfusiologie) benötigen Sie:</p>
                    <ul>
                        <li>Eine gültige Studienbescheinigung</li>
                        <li>Einen Bürgen (ordentliches, außerordentliches oder korrespondierendes Mitglied)</li>
                    </ul>
                </div>

                <div class="form-row">
                    <div class="form-group frm6 frm_first">
                        <label for="studienrichtung">Studienrichtung *</label>
                        <input type="text" id="studienrichtung" name="studienrichtung">
                        <small>Bitte geben Sie die Bezeichnung Ihres Studienganges an. Für eine ordentliche Mitgliedschaft ist ein Studiengang aus dem Bereich Technische Medizin/Perfusiologie anzugeben.</small>
                    </div>

                    <div class="form-group frm6">
                        <label for="studienbescheinigung">Studienbescheinigung hochladen *</label>
                        <input type="file" id="studienbescheinigung" name="studienbescheinigung" accept=".jpg,.jpeg,.png,.pdf">
                        <small>Erlaubte Formate: JPG, PNG, PDF (max. 5 MB)</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group frm6 frm_first">
                        <label for="studienbescheinigung_gueltig_bis">Die Studienbescheinigung ist gültig bis (Jahr) *</label>
                        <input type="number" id="studienbescheinigung_gueltig_bis" name="studienbescheinigung_gueltig_bis" min="2025" max="2030" step="1">
                    </div>
                </div>
            </div>

            <div id="non-student-info">
                <h3>Weitere Schritte</h3>
                <p>Im nächsten Schritt werden Sie gegebenenfalls nach Bürgen gefragt.</p>
            </div>

            <div class="form-navigation">
                <button type="button" class="btn-prev">Zurück</button>
                <button type="button" class="btn-next">Weiter</button>
            </div>
        </div>

        <!-- Step 4: Bürgen (dynamisch) -->
        <div class="form-step" data-step="4">
            <div id="guarantors-container">
                <h3>Bürgen angeben</h3>
                <div id="guarantor-intro-text"></div>

                <div class="guarantor-section" id="guarantor1-section">
                    <h4>Bürge 1 *</h4>
                    <div class="form-row">
                        <div class="form-group frm6 frm_first">
                            <label for="buerge1_input">Name oder E-Mail-Adresse *</label>
                            <div class="guarantor-input-wrapper">
                                <input type="text" id="buerge1_input" name="buerge1_input" placeholder="z.B. Hans Maier oder hans.maier@example.com">
                                <span class="guarantor-status" data-guarantor="1"></span>
                            </div>
                            <input type="hidden" id="buerge1_id" name="buerge1_id">
                            <input type="hidden" id="buerge1_name" name="buerge1_name">
                            <input type="hidden" id="buerge1_email" name="buerge1_email">
                            <small class="guarantor-info" id="buerge1_info"></small>
                        </div>
                    </div>
                </div>

                <div class="guarantor-section" id="guarantor2-section">
                    <h4>Bürge 2 *</h4>
                    <div class="form-row">
                        <div class="form-group frm6 frm_first">
                            <label for="buerge2_input">Name oder E-Mail-Adresse *</label>
                            <div class="guarantor-input-wrapper">
                                <input type="text" id="buerge2_input" name="buerge2_input" placeholder="z.B. Maria Schmidt oder maria.schmidt@example.com">
                                <span class="guarantor-status" data-guarantor="2"></span>
                            </div>
                            <input type="hidden" id="buerge2_id" name="buerge2_id">
                            <input type="hidden" id="buerge2_name" name="buerge2_name">
                            <input type="hidden" id="buerge2_email" name="buerge2_email">
                            <small class="guarantor-info" id="buerge2_info"></small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-navigation">
                <button type="button" class="btn-prev">Zurück</button>
                <button type="button" class="btn-next">Weiter</button>
            </div>
        </div>

        <!-- Step 5: Datenschutz und Bestätigung -->
        <div class="form-step" data-step="5">
            <h3>Datenschutz und Bestätigung</h3>

            <!-- 1. Satzung -->
            <div class="satzung-box confirmation-section">
                <h4>Anerkennung der Satzung</h4>
                <p class="info-text">
                    Gemäß § 4 Abs. 2 c) der Satzung erkennen Sie mit Ihrer Aufnahme die
                    <a href="https://www.dgptm.de/satzung" target="_blank">Satzung der DGPTM</a>
                    als verbindlich an.
                </p>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="satzung_akzeptiert" name="satzung_akzeptiert" value="1" required>
                        <span>Ich habe die Satzung der DGPTM gelesen und erkenne diese als verbindlich an. *</span>
                    </label>
                </div>
            </div>

            <!-- 2. Mitgliedsbeitrag -->
            <div class="beitrag-box confirmation-section">
                <h4>Mitgliedsbeitrag</h4>
                <p class="info-text">
                    Gemäß § 5 der Satzung werden von den Mitgliedern Jahresbeiträge erhoben.
                    Der Beitrag wird mittels SEPA-Lastschriftverfahren über unseren Dienstleister "GoCardless" eingezogen.
                    Sie können dies gleich hier erledigen: <a href="https://pay.gocardless.com/BRT0002ME4KNDCE" target="_blank">SEPA-Mandat erteilen</a>
                </p>
                <div class="beitrag-info">
                    <table class="beitrag-tabelle">
                        <tr>
                            <td>Ordentliche Mitglieder:</td>
                            <td><strong>70,00 € / Jahr</strong></td>
                        </tr>
                        <tr>
                            <td>Studentische Mitglieder:</td>
                            <td><strong>10,00 € / Jahr</strong></td>
                        </tr>
                        <tr>
                            <td>Außerordentliche Mitglieder:</td>
                            <td><strong>70,00 € / Jahr</strong></td>
                        </tr>
                        <tr>
                            <td>Fördermitglieder:</td>
                            <td><strong>750,00 €</strong></td>
                        </tr>
                    </table>
                    <p class="small-info">
                        Der Jahresbeitrag ist jeweils zum 01. März fällig (§ 5 Abs. 5).
                        Senior-Mitglieder sind beitragsfrei (§ 5 Abs. 2).
                    </p>
                </div>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="beitrag_akzeptiert" name="beitrag_akzeptiert" value="1" required>
                        <span>Ich bin mit der Erhebung des Mitgliedsbeitrags einverstanden und werde
                        der DGPTM nach Aufnahme ein SEPA-Lastschriftmandat erteilen. *</span>
                    </label>
                </div>
            </div>

            <!-- 3. Datenschutz (DSGVO) -->
            <div class="dsgvo-box confirmation-section">
                <h4>Datenschutz (DSGVO)</h4>
                <div class="dsgvo-text">
                    <p>
                        Im Falle des Erstkontakts sind wir gemäß Art. 12, 13 DSGVO verpflichtet,
                        Ihnen folgende datenschutzrechtliche Pflichtinformationen zur Verfügung zu stellen:
                        Wenn Sie uns per E-Mail kontaktieren, verarbeiten wir Ihre personenbezogenen Daten nur,
                        soweit an der Verarbeitung ein berechtigtes Interesse besteht (Art. 6 Abs. 1 lit. f DSGVO),
                        Sie in die Datenverarbeitung eingewilligt haben (Art. 6 Abs. 1 lit. a DSGVO),
                        die Verarbeitung für die Anbahnung, Begründung, inhaltliche Ausgestaltung oder Änderung
                        eines Rechtsverhältnisses zwischen Ihnen und uns erforderlich sind (Art. 6 Abs. 1 lit. b DSGVO)
                        oder eine sonstige Rechtsnorm die Verarbeitung gestattet.
                    </p>
                    <p>
                        Ihre personenbezogenen Daten verbleiben bei uns, bis Sie uns zur Löschung auffordern,
                        Ihre Einwilligung zur Speicherung widerrufen oder der Zweck für die Datenspeicherung entfällt
                        (z. B. nach abgeschlossener Bearbeitung Ihres Anliegens). Zwingende gesetzliche Bestimmungen
                        – insbesondere steuer- und handelsrechtliche Aufbewahrungsfristen – bleiben unberührt.
                    </p>
                    <p>
                        Sie haben jederzeit das Recht, unentgeltlich Auskunft über Herkunft, Empfänger und Zweck
                        Ihrer gespeicherten personenbezogenen Daten zu erhalten. Ihnen steht außerdem ein Recht
                        auf Widerspruch, auf Datenübertragbarkeit und ein Beschwerderecht bei der zuständigen
                        Aufsichtsbehörde zu. Ferner können Sie die Berichtigung, die Löschung und unter bestimmten
                        Umständen die Einschränkung der Verarbeitung Ihrer personenbezogenen Daten verlangen.
                    </p>
                    <p>
                        Details entnehmen Sie unserer
                        <a href="https://www.dgptm.de/datenschutz" target="_blank">Datenschutzerklärung</a>.
                        Unseren Datenschutzbeauftragten erreichen Sie unter datenschutz@dgptm.de.
                    </p>
                </div>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="dsgvo_akzeptiert" name="dsgvo_akzeptiert" value="1" required>
                        <span>Ich stimme der Verarbeitung meiner Daten im Rahmen meiner Mitgliedschaft
                        bei der DGPTM ausdrücklich zu. *</span>
                    </label>
                </div>
            </div>

            <div class="form-navigation">
                <button type="button" class="btn-prev">Zurück</button>
                <button type="submit" class="btn-submit">Antrag absenden</button>
            </div>
        </div>

    </form>

    <div id="success-message" class="success-message" style="display: none;">
        <div class="success-icon">✓</div>
        <h3>Vielen Dank für Ihren Mitgliedsantrag!</h3>
        <p>Ihr Antrag wurde erfolgreich eingereicht und wird nun von uns geprüft. Sie erhalten in Kürze eine Bestätigung per E-Mail.</p>
    </div>
</div>
