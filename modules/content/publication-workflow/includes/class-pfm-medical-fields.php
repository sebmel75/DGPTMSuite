<?php
/**
 * Publication Frontend Manager - Medical Publication Fields
 * Integration mit ACF-Feldern für medizinische Publikationen
 */

if (!defined('ABSPATH')) {
    exit;
}

class PFM_Medical_Fields {

    /**
     * ACF-Feldnamen Mapping
     */
    public static function get_acf_field_names() {
        return array(
            'publikationsart' => 'publikationsart',
            'kardiotechnikausgabe' => 'kardiotechnikausgabe',
            'supplement' => 'supplement',
            'doi' => 'doi',
            'unterueberschrift' => 'unterueberschrift',
            'volltext_anzeigen' => 'volltext_anzeigen',
            'pdf_editorial' => 'pdf-editorial',
            'pdf_tutorial' => 'pdf-tutorial',
            'pdf_supplement' => 'pdf-supplement',
            'pdf_externer_fachartikel' => 'pdf-externer-fachartikel',
            'pdf_kommentar_originalarbeit' => 'pdf-kommentar-originalarbeit',
            'pdf_abstract' => 'pdf-abstract',
            'pdf_volltext' => 'pdf-volltext',
            'autoren' => 'autoren',
            'hauptautorin' => 'hauptautorin',
            'keywords_deutsch' => 'keywords-deutsch',
            'abstract_deutsch' => 'abstract-deutsch',
            'keywords_englisch' => 'keywords-englisch',
            'abstract' => 'abstract',
            'literatur' => 'literatur',
        );
    }

    /**
     * Publikationsarten für medizinische Journals
     */
    public static function get_publikationsarten() {
        return array(
            'editorial' => __('Editorial', PFM_TD),
            'originalarbeit' => __('Originalarbeit', PFM_TD),
            'übersichtsarbeit' => __('Übersichtsarbeit', PFM_TD),
            'fallbericht' => __('Fallbericht', PFM_TD),
            'kommentar' => __('Kommentar', PFM_TD),
            'tutorial' => __('Tutorial', PFM_TD),
            'abstract' => __('Abstract', PFM_TD),
            'supplement' => __('Supplement', PFM_TD),
            'brief' => __('Brief an die Redaktion', PFM_TD),
        );
    }

    /**
     * Medizinisch-spezifische Review-Kriterien
     */
    public static function get_medical_review_criteria() {
        return array(
            'clinical_relevance' => array(
                'label' => __('Klinische Relevanz', PFM_TD),
                'description' => __('Bedeutung für die klinische Praxis und Patientenversorgung', PFM_TD),
                'weight' => 25,
            ),
            'methodology' => array(
                'label' => __('Studiendesign & Methodik', PFM_TD),
                'description' => __('Qualität des Studiendesigns, Methodik und statistischer Analyse', PFM_TD),
                'weight' => 25,
            ),
            'ethical_standards' => array(
                'label' => __('Ethische Standards', PFM_TD),
                'description' => __('Einhaltung ethischer Richtlinien, Patientenschutz, Informed Consent', PFM_TD),
                'weight' => 15,
            ),
            'data_quality' => array(
                'label' => __('Datenqualität & -präsentation', PFM_TD),
                'description' => __('Qualität und Vollständigkeit der Daten, statistische Auswertung', PFM_TD),
                'weight' => 15,
            ),
            'literature' => array(
                'label' => __('Literatur & Evidenz', PFM_TD),
                'description' => __('Aktualität und Vollständigkeit der zitierten Literatur', PFM_TD),
                'weight' => 10,
            ),
            'presentation' => array(
                'label' => __('Darstellung & Sprache', PFM_TD),
                'description' => __('Klarheit, Struktur und sprachliche Qualität', PFM_TD),
                'weight' => 10,
            ),
        );
    }

    /**
     * Hole ACF-Wert (kompatibel mit Fallback)
     */
    public static function get_field_value($post_id, $field_name) {
        // Prüfe ACF-Feld
        if (function_exists('get_field')) {
            $value = get_field($field_name, $post_id);
            if ($value !== false && $value !== null) {
                return $value;
            }
        }

        // Fallback auf post_meta
        return get_post_meta($post_id, $field_name, true);
    }

    /**
     * Speichere ACF-Wert (kompatibel mit Fallback)
     */
    public static function update_field_value($post_id, $field_name, $value) {
        if (function_exists('update_field')) {
            return update_field($field_name, $value, $post_id);
        }

        return update_post_meta($post_id, $field_name, $value);
    }

    /**
     * Render Medical Publication Info
     */
    public static function render_publication_info($post_id) {
        $fields = self::get_acf_field_names();

        $publikationsart = self::get_field_value($post_id, $fields['publikationsart']);
        $kardiotechnikausgabe = self::get_field_value($post_id, $fields['kardiotechnikausgabe']);
        $supplement = self::get_field_value($post_id, $fields['supplement']);
        $doi = self::get_field_value($post_id, $fields['doi']);
        $unterueberschrift = self::get_field_value($post_id, $fields['unterueberschrift']);
        $autoren = self::get_field_value($post_id, $fields['autoren']);
        $hauptautorin = self::get_field_value($post_id, $fields['hauptautorin']);

        ob_start();
        ?>
        <div class="pfm-medical-publication-info">
            <div class="publication-metadata">
                <?php if ($publikationsart): ?>
                    <div class="meta-item publikationsart">
                        <span class="meta-label"><?php _e('Publikationsart:', PFM_TD); ?></span>
                        <span class="meta-value publikationsart-badge"><?php echo esc_html(self::get_publikationsarten()[$publikationsart] ?? $publikationsart); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($kardiotechnikausgabe): ?>
                    <div class="meta-item">
                        <span class="meta-label"><?php _e('Kardiotechnik Ausgabe:', PFM_TD); ?></span>
                        <span class="meta-value"><?php echo esc_html($kardiotechnikausgabe); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($supplement): ?>
                    <div class="meta-item">
                        <span class="meta-label"><?php _e('Supplement:', PFM_TD); ?></span>
                        <span class="meta-value"><?php echo esc_html($supplement); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($doi): ?>
                    <div class="meta-item doi">
                        <span class="meta-label">DOI:</span>
                        <span class="meta-value">
                            <a href="https://doi.org/<?php echo esc_attr($doi); ?>" target="_blank" rel="noopener">
                                <?php echo esc_html($doi); ?>
                            </a>
                        </span>
                    </div>
                <?php endif; ?>

                <?php if ($unterueberschrift): ?>
                    <div class="meta-item subtitle">
                        <span class="meta-value"><?php echo esc_html($unterueberschrift); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($autoren): ?>
                    <div class="meta-item authors">
                        <span class="meta-label"><?php _e('Autoren:', PFM_TD); ?></span>
                        <span class="meta-value"><?php echo esc_html($autoren); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($hauptautorin): ?>
                    <div class="meta-item corresponding">
                        <span class="meta-label"><?php _e('Korrespondenzautor:', PFM_TD); ?></span>
                        <span class="meta-value"><?php echo esc_html($hauptautorin); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render Abstracts (Deutsch & Englisch)
     */
    public static function render_abstracts($post_id) {
        $fields = self::get_acf_field_names();

        $abstract_deutsch = self::get_field_value($post_id, $fields['abstract_deutsch']);
        $keywords_deutsch = self::get_field_value($post_id, $fields['keywords_deutsch']);
        $abstract = self::get_field_value($post_id, $fields['abstract']);
        $keywords_englisch = self::get_field_value($post_id, $fields['keywords_englisch']);

        ob_start();
        ?>
        <div class="pfm-abstracts">
            <?php if ($abstract_deutsch): ?>
                <div class="abstract-section german">
                    <h4><?php _e('Zusammenfassung', PFM_TD); ?></h4>
                    <div class="abstract-text"><?php echo wp_kses_post(nl2br($abstract_deutsch)); ?></div>
                    <?php if ($keywords_deutsch): ?>
                        <div class="keywords">
                            <strong><?php _e('Schlüsselwörter:', PFM_TD); ?></strong>
                            <?php echo esc_html($keywords_deutsch); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($abstract): ?>
                <div class="abstract-section english">
                    <h4>Abstract</h4>
                    <div class="abstract-text"><?php echo wp_kses_post(nl2br($abstract)); ?></div>
                    <?php if ($keywords_englisch): ?>
                        <div class="keywords">
                            <strong>Keywords:</strong>
                            <?php echo esc_html($keywords_englisch); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render Medical PDF Downloads
     */
    public static function render_pdf_downloads($post_id) {
        $fields = self::get_acf_field_names();

        $pdfs = array(
            'pdf_volltext' => __('Volltext (PDF)', PFM_TD),
            'pdf_abstract' => __('Abstract (PDF)', PFM_TD),
            'pdf_editorial' => __('Editorial (PDF)', PFM_TD),
            'pdf_tutorial' => __('Tutorial (PDF)', PFM_TD),
            'pdf_supplement' => __('Supplement (PDF)', PFM_TD),
            'pdf_externer_fachartikel' => __('Externer Fachartikel (PDF)', PFM_TD),
            'pdf_kommentar_originalarbeit' => __('Kommentar zur Originalarbeit (PDF)', PFM_TD),
        );

        $available_pdfs = array();

        foreach ($pdfs as $field_key => $label) {
            $pdf = self::get_field_value($post_id, $fields[$field_key]);
            if ($pdf) {
                $available_pdfs[$field_key] = array(
                    'label' => $label,
                    'data' => $pdf,
                );
            }
        }

        if (empty($available_pdfs)) {
            return '';
        }

        ob_start();
        ?>
        <div class="pfm-pdf-downloads">
            <h4><?php _e('Downloads', PFM_TD); ?></h4>
            <div class="pdf-grid">
                <?php foreach ($available_pdfs as $key => $pdf_data): ?>
                    <?php
                    // ACF gibt Arrays zurück bei image-Feldern
                    $url = is_array($pdf_data['data']) ? $pdf_data['data']['url'] : $pdf_data['data'];
                    $filename = is_array($pdf_data['data']) ? $pdf_data['data']['filename'] : basename($url);
                    ?>
                    <div class="pdf-item">
                        <a href="<?php echo esc_url($url); ?>" target="_blank" class="pdf-link" rel="noopener">
                            <span class="dashicons dashicons-pdf"></span>
                            <span class="pdf-label"><?php echo esc_html($pdf_data['label']); ?></span>
                            <span class="pdf-filename"><?php echo esc_html($filename); ?></span>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render Literatur
     */
    public static function render_literature($post_id) {
        $fields = self::get_acf_field_names();
        $literatur = self::get_field_value($post_id, $fields['literatur']);

        if (!$literatur) {
            return '';
        }

        ob_start();
        ?>
        <div class="pfm-literatur">
            <h4><?php _e('Literatur', PFM_TD); ?></h4>
            <div class="literatur-text">
                <?php echo wp_kses_post(nl2br($literatur)); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render Medical Submission Form
     */
    public static function render_medical_submission_form() {
        ob_start();
        ?>
        <div class="pfm-medical-submission-form">
            <h3><?php _e('Manuskript einreichen', PFM_TD); ?></h3>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field('pfm_medical_submission', 'pfm_medical_submission_nonce'); ?>
                <input type="hidden" name="action" value="pfm_medical_submission">

                <div class="form-section">
                    <h4><?php _e('Basisdaten', PFM_TD); ?></h4>

                    <p>
                        <label for="publication_title"><strong><?php _e('Titel der Publikation *', PFM_TD); ?></strong></label>
                        <input type="text" id="publication_title" name="publication_title" required class="large-text">
                    </p>

                    <p>
                        <label for="unterueberschrift"><strong><?php _e('Untertitel', PFM_TD); ?></strong></label>
                        <input type="text" id="unterueberschrift" name="unterueberschrift" class="large-text">
                    </p>

                    <p>
                        <label for="publikationsart"><strong><?php _e('Publikationsart *', PFM_TD); ?></strong></label>
                        <select id="publikationsart" name="publikationsart" required class="large-text">
                            <option value=""><?php _e('-- Bitte wählen --', PFM_TD); ?></option>
                            <?php foreach (self::get_publikationsarten() as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </p>

                    <p>
                        <label for="kardiotechnikausgabe"><strong><?php _e('Kardiotechnik Ausgabe', PFM_TD); ?></strong></label>
                        <input type="text" id="kardiotechnikausgabe" name="kardiotechnikausgabe" class="large-text" placeholder="z.B. 4/2024">
                    </p>
                </div>

                <div class="form-section">
                    <h4><?php _e('Autoren', PFM_TD); ?></h4>

                    <p>
                        <label for="autoren"><strong><?php _e('Autoren (vollständig) *', PFM_TD); ?></strong></label>
                        <textarea id="autoren" name="autoren" rows="3" class="large-text" required placeholder="Nachname, Vorname¹; Nachname, Vorname²"></textarea>
                        <span class="description"><?php _e('Bitte alle Autoren mit Vor- und Nachnamen angeben, getrennt durch Semikolon.', PFM_TD); ?></span>
                    </p>

                    <p>
                        <label for="hauptautorin"><strong><?php _e('Korrespondenzautor *', PFM_TD); ?></strong></label>
                        <input type="text" id="hauptautorin" name="hauptautorin" required class="large-text" placeholder="inkl. E-Mail-Adresse">
                    </p>
                </div>

                <div class="form-section">
                    <h4><?php _e('Abstract & Keywords', PFM_TD); ?></h4>

                    <p>
                        <label for="abstract_deutsch"><strong><?php _e('Zusammenfassung (Deutsch) *', PFM_TD); ?></strong></label>
                        <textarea id="abstract_deutsch" name="abstract_deutsch" rows="8" class="large-text" required maxlength="2000"></textarea>
                        <span class="description"><?php _e('Max. 2000 Zeichen', PFM_TD); ?></span>
                    </p>

                    <p>
                        <label for="keywords_deutsch"><strong><?php _e('Schlüsselwörter (Deutsch) *', PFM_TD); ?></strong></label>
                        <input type="text" id="keywords_deutsch" name="keywords_deutsch" required class="large-text" placeholder="3-6 Schlüsselwörter, durch Komma getrennt">
                    </p>

                    <p>
                        <label for="abstract"><strong><?php _e('Abstract (English) *', PFM_TD); ?></strong></label>
                        <textarea id="abstract" name="abstract" rows="8" class="large-text" required maxlength="2000"></textarea>
                        <span class="description"><?php _e('Max. 2000 characters', PFM_TD); ?></span>
                    </p>

                    <p>
                        <label for="keywords_englisch"><strong><?php _e('Keywords (English) *', PFM_TD); ?></strong></label>
                        <input type="text" id="keywords_englisch" name="keywords_englisch" required class="large-text" placeholder="3-6 keywords, comma separated">
                    </p>
                </div>

                <div class="form-section">
                    <h4><?php _e('Manuskript & Zusatzmaterialien', PFM_TD); ?></h4>

                    <p>
                        <label for="manuscript_pdf"><strong><?php _e('Manuskript (PDF) *', PFM_TD); ?></strong></label>
                        <input type="file" id="manuscript_pdf" name="manuscript_pdf" accept="application/pdf" required>
                        <span class="description"><?php _e('Bitte laden Sie das vollständige Manuskript als PDF hoch.', PFM_TD); ?></span>
                    </p>

                    <p>
                        <label for="supplement_files"><strong><?php _e('Zusatzmaterialien (optional)', PFM_TD); ?></strong></label>
                        <input type="file" id="supplement_files" name="supplement_files[]" multiple>
                        <span class="description"><?php _e('Tabellen, Abbildungen, Supplementary Material (mehrere Dateien möglich)', PFM_TD); ?></span>
                    </p>
                </div>

                <div class="form-section">
                    <h4><?php _e('Literatur', PFM_TD); ?></h4>

                    <p>
                        <label for="literatur"><strong><?php _e('Literaturverzeichnis', PFM_TD); ?></strong></label>
                        <textarea id="literatur" name="literatur" rows="10" class="large-text"></textarea>
                        <span class="description"><?php _e('Literaturangaben nach den Richtlinien der Zeitschrift', PFM_TD); ?></span>
                    </p>
                </div>

                <div class="form-section">
                    <h4><?php _e('Erklärungen', PFM_TD); ?></h4>

                    <p>
                        <label>
                            <input type="checkbox" name="ethics_approval" value="1" required>
                            <strong><?php _e('Ethik-Votum *', PFM_TD); ?></strong>
                        </label><br>
                        <span class="description"><?php _e('Hiermit bestätige ich, dass für diese Studie ein positives Ethik-Votum vorliegt bzw. nicht erforderlich ist.', PFM_TD); ?></span>
                    </p>

                    <p>
                        <label>
                            <input type="checkbox" name="informed_consent" value="1" required>
                            <strong><?php _e('Einwilligung der Patienten *', PFM_TD); ?></strong>
                        </label><br>
                        <span class="description"><?php _e('Hiermit bestätige ich, dass alle Patienten ihre Einwilligung zur Teilnahme und Veröffentlichung gegeben haben.', PFM_TD); ?></span>
                    </p>

                    <p>
                        <label>
                            <input type="checkbox" name="no_plagiarism" value="1" required>
                            <strong><?php _e('Originalarbeit *', PFM_TD); ?></strong>
                        </label><br>
                        <span class="description"><?php _e('Hiermit versichere ich, dass es sich um eine Originalarbeit handelt und keine Urheberrechte verletzt werden.', PFM_TD); ?></span>
                    </p>

                    <p>
                        <label>
                            <input type="checkbox" name="conflict_of_interest" value="0">
                            <strong><?php _e('Interessenkonflikt', PFM_TD); ?></strong>
                        </label><br>
                        <span class="description"><?php _e('Bitte ankreuzen, falls ein Interessenkonflikt vorliegt (Details im nächsten Feld)', PFM_TD); ?></span>
                    </p>

                    <p id="conflict_details_field" style="display:none;">
                        <label for="conflict_details"><strong><?php _e('Details zum Interessenkonflikt', PFM_TD); ?></strong></label>
                        <textarea id="conflict_details" name="conflict_details" rows="4" class="large-text"></textarea>
                    </p>
                </div>

                <p class="submit">
                    <button type="submit" class="button button-primary button-large">
                        <?php _e('Manuskript einreichen', PFM_TD); ?>
                    </button>
                </p>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('input[name="conflict_of_interest"]').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#conflict_details_field').slideDown();
                } else {
                    $('#conflict_details_field').slideUp();
                }
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Render Medical Edit Form (für Redaktion)
     */
    public static function render_medical_edit_form($post_id) {
        if (!pfm_user_is_editor_in_chief() && !pfm_user_is_redaktion()) {
            return '<p>' . __('Keine Berechtigung.', PFM_TD) . '</p>';
        }

        $fields = self::get_acf_field_names();

        ob_start();
        ?>
        <div class="pfm-medical-edit-form">
            <h4><?php _e('Publikationsdaten bearbeiten', PFM_TD); ?></h4>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('pfm_update_medical_fields', 'pfm_medical_nonce'); ?>
                <input type="hidden" name="action" value="pfm_update_medical_fields">
                <input type="hidden" name="post_id" value="<?php echo esc_attr($post_id); ?>">

                <table class="form-table">
                    <tr>
                        <th><label for="publikationsart"><?php _e('Publikationsart', PFM_TD); ?></label></th>
                        <td>
                            <select name="publikationsart" id="publikationsart" class="regular-text">
                                <?php foreach (self::get_publikationsarten() as $key => $label): ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected(self::get_field_value($post_id, $fields['publikationsart']), $key); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="kardiotechnikausgabe"><?php _e('Kardiotechnik Ausgabe', PFM_TD); ?></label></th>
                        <td>
                            <input type="text" name="kardiotechnikausgabe" id="kardiotechnikausgabe"
                                   value="<?php echo esc_attr(self::get_field_value($post_id, $fields['kardiotechnikausgabe'])); ?>"
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="supplement"><?php _e('Supplement', PFM_TD); ?></label></th>
                        <td>
                            <input type="text" name="supplement" id="supplement"
                                   value="<?php echo esc_attr(self::get_field_value($post_id, $fields['supplement'])); ?>"
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="doi">DOI</label></th>
                        <td>
                            <input type="text" name="doi" id="doi"
                                   value="<?php echo esc_attr(self::get_field_value($post_id, $fields['doi'])); ?>"
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="volltext_anzeigen"><?php _e('Volltext anzeigen', PFM_TD); ?></label></th>
                        <td>
                            <select name="volltext_anzeigen" id="volltext_anzeigen">
                                <option value="ja" <?php selected(self::get_field_value($post_id, $fields['volltext_anzeigen']), 'ja'); ?>><?php _e('Ja', PFM_TD); ?></option>
                                <option value="nein" <?php selected(self::get_field_value($post_id, $fields['volltext_anzeigen']), 'nein'); ?>><?php _e('Nein', PFM_TD); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php _e('Änderungen speichern', PFM_TD); ?>
                    </button>
                </p>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Zitierformat für medizinische Publikationen
     */
    public static function get_citation_format($post_id) {
        $fields = self::get_acf_field_names();

        $autoren = self::get_field_value($post_id, $fields['autoren']);
        $title = get_the_title($post_id);
        $ausgabe = self::get_field_value($post_id, $fields['kardiotechnikausgabe']);
        $doi = self::get_field_value($post_id, $fields['doi']);
        $jahr = get_the_date('Y', $post_id);

        $citation = '';
        if ($autoren) {
            $citation .= $autoren . '. ';
        }
        if ($title) {
            $citation .= $title . '. ';
        }
        $citation .= 'Kardiotechnik';
        if ($ausgabe) {
            $citation .= ' ' . $ausgabe;
        }
        $citation .= ' (' . $jahr . ')';
        if ($doi) {
            $citation .= '. DOI: ' . $doi;
        }

        return $citation;
    }
}
