<?php
/**
 * Template: Reviewer Upload Form
 *
 * Available variables:
 * - $atts: Shortcode attributes
 * - $token: The upload token string
 * - $token_data: Token data array with publication info
 * - $publication: WP_Post object of the publication
 * - $publication_id: Publication post ID
 * - $autoren: Authors string
 * - $abstract: Abstract text
 * - $submission_date: Submission date formatted
 * - $current_version: Current manuscript version info or null
 */

if (!defined('ABSPATH')) {
    exit;
}

$type_labels = array(
    'gutachten' => __('Gutachten', PFM_TD),
    'revision' => __('Revision', PFM_TD),
    'autor' => __('Überarbeitete Version', PFM_TD),
);

$type_label = isset($type_labels[$token_data['token_type']]) ? $type_labels[$token_data['token_type']] : __('Datei', PFM_TD);
?>

<div class="pfm-reviewer-upload">
    <div class="pfm-reviewer-header">
        <h2><?php echo esc_html($atts['title']); ?></h2>
    </div>

    <div class="pfm-reviewer-publication-info">
        <div class="publication-title">
            <label><?php _e('Artikel:', PFM_TD); ?></label>
            <span><?php echo esc_html($publication->post_title); ?></span>
        </div>

        <?php if ($autoren): ?>
        <div class="publication-authors">
            <label><?php _e('Autor(en):', PFM_TD); ?></label>
            <span><?php echo esc_html($autoren); ?></span>
        </div>
        <?php endif; ?>

        <div class="publication-date">
            <label><?php _e('Eingereicht am:', PFM_TD); ?></label>
            <span><?php echo esc_html($submission_date); ?></span>
        </div>

        <?php if ($abstract): ?>
        <div class="publication-abstract">
            <label><?php _e('Abstract:', PFM_TD); ?></label>
            <div class="abstract-text"><?php echo wp_kses_post(nl2br($abstract)); ?></div>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($current_version): ?>
    <div class="pfm-reviewer-download">
        <h3><?php _e('Manuskript herunterladen', PFM_TD); ?></h3>
        <p class="description"><?php _e('Bitte laden Sie das Manuskript herunter und begutachten Sie es.', PFM_TD); ?></p>

        <a href="<?php echo esc_url(add_query_arg(array(
            'action' => 'pfm_reviewer_download',
            'token' => $token,
            'source' => $current_version['source'],
            'version_id' => $current_version['id'],
            'nonce' => wp_create_nonce('pfm_reviewer_nonce'),
        ), admin_url('admin-ajax.php'))); ?>" class="pfm-download-button" target="_blank">
            <span class="dashicons dashicons-pdf"></span>
            <span class="filename"><?php echo esc_html($current_version['filename']); ?></span>
            <span class="version">(V<?php echo esc_html($current_version['version']); ?>)</span>
        </a>
    </div>
    <?php endif; ?>

    <div class="pfm-reviewer-divider"></div>

    <div class="pfm-reviewer-form-section">
        <h3><?php printf(__('Ihr %s hochladen', PFM_TD), esc_html($type_label)); ?></h3>

        <form id="pfm-reviewer-upload-form" class="pfm-reviewer-form" enctype="multipart/form-data">
            <input type="hidden" name="action" value="pfm_reviewer_upload">
            <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('pfm_reviewer_nonce'); ?>">

            <?php if (!empty($token_data['reviewer_name'])): ?>
                <input type="hidden" name="reviewer_name" value="<?php echo esc_attr($token_data['reviewer_name']); ?>">
                <input type="hidden" name="reviewer_email" value="<?php echo esc_attr($token_data['reviewer_email']); ?>">
                <div class="form-field form-field-readonly">
                    <label><?php _e('Ihr Name:', PFM_TD); ?></label>
                    <span class="field-value"><?php echo esc_html($token_data['reviewer_name']); ?></span>
                </div>
            <?php else: ?>
                <div class="form-field form-field-required">
                    <label for="reviewer_name"><?php _e('Ihr Name', PFM_TD); ?> <span class="required">*</span></label>
                    <input type="text" id="reviewer_name" name="reviewer_name" required placeholder="<?php esc_attr_e('Vorname Nachname', PFM_TD); ?>">
                </div>

                <div class="form-field form-field-required">
                    <label for="reviewer_email"><?php _e('Ihre E-Mail', PFM_TD); ?> <span class="required">*</span></label>
                    <input type="email" id="reviewer_email" name="reviewer_email" required placeholder="<?php esc_attr_e('ihre.email@beispiel.de', PFM_TD); ?>">
                </div>
            <?php endif; ?>

            <div class="form-field form-field-file form-field-required">
                <label for="reviewer_file"><?php _e('Datei', PFM_TD); ?> <span class="required">*</span></label>
                <div class="file-upload-wrapper">
                    <input type="file" id="reviewer_file" name="file" required accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
                    <div class="file-upload-info">
                        <span class="dashicons dashicons-upload"></span>
                        <span class="upload-text"><?php _e('Datei auswählen oder hierher ziehen', PFM_TD); ?></span>
                        <span class="file-name"></span>
                    </div>
                </div>
                <p class="description"><?php _e('PDF oder DOCX, max. 20 MB', PFM_TD); ?></p>
            </div>

            <div class="form-field form-field-textarea">
                <label for="reviewer_notes"><?php _e('Kommentar', PFM_TD); ?></label>
                <textarea id="reviewer_notes" name="notes" rows="4" placeholder="<?php esc_attr_e('Optionale Anmerkungen zu Ihrem Gutachten...', PFM_TD); ?>"></textarea>
            </div>

            <div class="form-field form-field-submit">
                <button type="submit" class="pfm-submit-button">
                    <span class="button-text"><?php printf(__('%s absenden', PFM_TD), esc_html($type_label)); ?></span>
                    <span class="button-loading" style="display: none;">
                        <span class="spinner"></span>
                        <?php _e('Wird hochgeladen...', PFM_TD); ?>
                    </span>
                </button>
            </div>
        </form>
    </div>

    <div class="pfm-reviewer-success" style="display: none;">
        <div class="success-icon">
            <span class="dashicons dashicons-yes-alt"></span>
        </div>
        <div class="success-content">
            <h3><?php _e('Vielen Dank!', PFM_TD); ?></h3>
            <p><?php printf(__('Ihr %s wurde erfolgreich hochgeladen.', PFM_TD), esc_html(strtolower($type_label))); ?></p>
            <p class="success-note"><?php _e('Die Redaktion wurde benachrichtigt und wird sich bei Bedarf bei Ihnen melden.', PFM_TD); ?></p>
        </div>
    </div>

    <div class="pfm-reviewer-footer">
        <p class="pfm-privacy-note">
            <?php _e('Ihre Daten werden gemäß unserer Datenschutzrichtlinie verarbeitet.', PFM_TD); ?>
        </p>
    </div>
</div>
