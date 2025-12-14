<?php
/**
 * Template: Artikel-Einreichungsformular
 * Shortcode: [artikel_einreichung]
 */

if (!defined('ABSPATH')) exit;

$user = wp_get_current_user();
$publikationsarten = DGPTM_Artikel_Einreichung::PUBLIKATIONSARTEN;
?>

<div class="dgptm-artikel-container">
    <form id="artikel-submission-form" class="dgptm-artikel-form" enctype="multipart/form-data">

        <h2>Artikel einreichen</h2>
        <p class="form-intro">
            Reichen Sie Ihren Artikel für die Fachzeitschrift "Die Perfusiologie" ein.
            Pflichtfelder sind mit <span class="required">*</span> gekennzeichnet.
        </p>

        <!-- Progress Steps -->
        <ul class="progress-steps">
            <li class="progress-step active">
                <span class="step-number">1</span>
                <span class="step-label">Grunddaten</span>
            </li>
            <li class="progress-step">
                <span class="step-number">2</span>
                <span class="step-label">Autoren</span>
            </li>
            <li class="progress-step">
                <span class="step-number">3</span>
                <span class="step-label">Inhalt</span>
            </li>
            <li class="progress-step">
                <span class="step-number">4</span>
                <span class="step-label">Dateien</span>
            </li>
        </ul>

        <!-- Section 1: Grunddaten -->
        <div class="form-section">
            <h3>1. Grunddaten des Artikels</h3>

            <div class="form-row">
                <label for="titel">Titel des Artikels <span class="required">*</span></label>
                <input type="text" id="titel" name="titel" required>
            </div>

            <div class="form-row">
                <label for="unterueberschrift">Untertitel</label>
                <input type="text" id="unterueberschrift" name="unterueberschrift">
            </div>

            <div class="form-row">
                <label for="publikationsart">Art der Publikation <span class="required">*</span></label>
                <select id="publikationsart" name="publikationsart" required>
                    <option value="">-- Bitte wählen --</option>
                    <?php foreach ($publikationsarten as $key => $label): ?>
                        <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description">Wählen Sie die Art Ihrer Einreichung.</p>
            </div>
        </div>

        <!-- Section 2: Autoren -->
        <div class="form-section">
            <h3>2. Autorenangaben</h3>

            <div class="form-row-inline">
                <div class="form-row">
                    <label for="hauptautor">Korrespondenzautor/in <span class="required">*</span></label>
                    <input type="text" id="hauptautor" name="hauptautor" required
                           value="<?php echo esc_attr($user->display_name); ?>">
                </div>
                <div class="form-row">
                    <label for="hauptautor_email">E-Mail <span class="required">*</span></label>
                    <input type="email" id="hauptautor_email" name="hauptautor_email" required
                           value="<?php echo esc_attr($user->user_email); ?>">
                </div>
            </div>

            <div class="form-row">
                <label for="hauptautor_institution">Institution / Klinik</label>
                <input type="text" id="hauptautor_institution" name="hauptautor_institution">
            </div>

            <div class="form-row">
                <label for="koautoren">Ko-Autoren</label>
                <textarea id="koautoren" name="koautoren" rows="4"
                          placeholder="Ein Autor pro Zeile: Name, Institution"></textarea>
                <p class="description">Listen Sie alle Ko-Autoren mit ihrer Zugehörigkeit auf (ein Autor pro Zeile).</p>
            </div>
        </div>

        <!-- Section 3: Inhalt -->
        <div class="form-section">
            <h3>3. Zusammenfassung und Schlüsselwörter</h3>

            <div class="form-row">
                <label for="abstract_deutsch">Abstract (Deutsch) <span class="required">*</span></label>
                <textarea id="abstract_deutsch" name="abstract_deutsch" rows="6" required
                          placeholder="Max. 250 Wörter"></textarea>
            </div>

            <div class="form-row">
                <label for="abstract_englisch">Abstract (English)</label>
                <textarea id="abstract_englisch" name="abstract_englisch" rows="6"
                          placeholder="Max. 250 words"></textarea>
            </div>

            <div class="form-row-inline">
                <div class="form-row">
                    <label for="keywords_deutsch">Schlüsselwörter (Deutsch)</label>
                    <input type="text" id="keywords_deutsch" name="keywords_deutsch"
                           placeholder="z.B. Perfusion, Kardiochirurgie, ECMO">
                    <p class="description">Kommagetrennt, max. 6 Begriffe</p>
                </div>
                <div class="form-row">
                    <label for="keywords_englisch">Keywords (English)</label>
                    <input type="text" id="keywords_englisch" name="keywords_englisch"
                           placeholder="e.g. Perfusion, Cardiac Surgery, ECMO">
                    <p class="description">Comma-separated, max. 6 terms</p>
                </div>
            </div>

            <div class="form-row">
                <label for="literatur">Literaturverzeichnis</label>
                <textarea id="literatur" name="literatur" rows="6"
                          placeholder="Bitte nach Vancouver-Stil formatieren"></textarea>
                <p class="description">Nummerierte Liste der zitierten Literatur</p>
            </div>
        </div>

        <!-- Section 4: Dateien -->
        <div class="form-section">
            <h3>4. Dateien hochladen</h3>

            <div class="form-row">
                <label>Manuskript (PDF oder Word) <span class="required">*</span></label>
                <div class="file-upload-area">
                    <input type="file" name="manuskript" accept=".pdf,.doc,.docx" required>
                    <div class="upload-icon">&#128196;</div>
                    <div class="upload-text">Klicken oder Datei hierher ziehen</div>
                    <div class="upload-hint">PDF, DOC oder DOCX (max. 20 MB)</div>
                </div>
                <div class="file-preview-container"></div>
            </div>

            <div class="form-row">
                <label>Abbildungen</label>
                <div class="file-upload-area">
                    <input type="file" name="abbildungen[]" accept=".jpg,.jpeg,.png,.tiff,.eps" multiple>
                    <div class="upload-icon">&#128247;</div>
                    <div class="upload-text">Klicken oder Dateien hierher ziehen</div>
                    <div class="upload-hint">JPG, PNG, TIFF oder EPS (min. 300 dpi)</div>
                </div>
                <div class="file-preview-container"></div>
            </div>

            <div class="form-row">
                <label>Tabellen (optional)</label>
                <div class="file-upload-area">
                    <input type="file" name="tabellen" accept=".xlsx,.xls,.doc,.docx">
                    <div class="upload-icon">&#128200;</div>
                    <div class="upload-text">Klicken oder Datei hierher ziehen</div>
                    <div class="upload-hint">Excel oder Word</div>
                </div>
                <div class="file-preview-container"></div>
            </div>

            <div class="form-row">
                <label>Supplementary Material (optional)</label>
                <div class="file-upload-area">
                    <input type="file" name="supplement">
                    <div class="upload-icon">&#128193;</div>
                    <div class="upload-text">Klicken oder Datei hierher ziehen</div>
                    <div class="upload-hint">Zusätzliche Dateien, Videos, etc.</div>
                </div>
                <div class="file-preview-container"></div>
            </div>
        </div>

        <!-- Section 5: Erklärungen -->
        <div class="form-section">
            <h3>5. Erklärungen</h3>

            <div class="form-row">
                <label for="interessenkonflikte">Interessenkonflikte</label>
                <textarea id="interessenkonflikte" name="interessenkonflikte" rows="3"
                          placeholder="Bitte alle potenziellen Interessenkonflikte angeben oder 'Keine' eintragen"></textarea>
            </div>

            <div class="form-row">
                <label for="funding">Förderung / Finanzierung</label>
                <textarea id="funding" name="funding" rows="2"
                          placeholder="Angaben zu Drittmitteln, Sponsoring, etc."></textarea>
            </div>

            <div class="form-row">
                <label for="ethikvotum">Ethikvotum (falls zutreffend)</label>
                <input type="text" id="ethikvotum" name="ethikvotum"
                       placeholder="Aktenzeichen der Ethikkommission">
            </div>

            <div class="form-row">
                <label>
                    <input type="checkbox" name="confirmation" required>
                    Ich bestätige, dass der Artikel nicht zeitgleich bei einer anderen Zeitschrift eingereicht wurde
                    und alle Autoren der Einreichung zugestimmt haben. <span class="required">*</span>
                </label>
            </div>
        </div>

        <div class="btn-group">
            <button type="submit" class="btn btn-primary">Artikel einreichen</button>
        </div>

    </form>
</div>

<style>
.submission-success {
    text-align: center;
    padding: 60px 20px;
}
.submission-success .success-icon {
    width: 80px;
    height: 80px;
    background: #38a169;
    color: #fff;
    font-size: 48px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 20px;
}
.submission-success h3 {
    color: #22543d;
    margin-bottom: 15px;
}
</style>
