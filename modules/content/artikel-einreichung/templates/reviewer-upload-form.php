<?php
/**
 * Template: Reviewer Upload Form
 * DGPTM Artikel-Einreichung
 *
 * Available variables:
 * - $atts: Shortcode attributes
 * - $token: The upload token string
 * - $token_data: Token data array with article info
 * - $artikel: WP_Post object of the article
 * - $artikel_id: Article post ID
 * - $autoren: Authors string
 * - $abstract: Abstract text
 * - $submission_date: Submission date
 * - $current_version: Current manuscript version info or null
 */

if (!defined('ABSPATH')) {
    exit;
}

$type_labels = array(
    'gutachten' => __('Gutachten', 'dgptm-artikel-einreichung'),
    'revision' => __('Revision', 'dgptm-artikel-einreichung'),
    'autor' => __('Überarbeitete Version', 'dgptm-artikel-einreichung'),
);

$type_label = isset($type_labels[$token_data['token_type']]) ? $type_labels[$token_data['token_type']] : __('Datei', 'dgptm-artikel-einreichung');
?>

<div class="dgptm-reviewer-upload">
    <div class="dgptm-reviewer-header">
        <h2><?php echo esc_html($atts['title']); ?></h2>
    </div>

    <div class="dgptm-reviewer-article-info">
        <div class="article-title">
            <label><?php _e('Artikel:', 'dgptm-artikel-einreichung'); ?></label>
            <span><?php echo esc_html($artikel->post_title); ?></span>
        </div>

        <?php if ($autoren): ?>
        <div class="article-authors">
            <label><?php _e('Autor(en):', 'dgptm-artikel-einreichung'); ?></label>
            <span><?php echo esc_html($autoren); ?></span>
        </div>
        <?php endif; ?>

        <?php if ($submission_date): ?>
        <div class="article-date">
            <label><?php _e('Eingereicht am:', 'dgptm-artikel-einreichung'); ?></label>
            <span><?php echo esc_html(date_i18n('d.m.Y', strtotime($submission_date))); ?></span>
        </div>
        <?php endif; ?>

        <?php if ($abstract): ?>
        <div class="article-abstract">
            <label><?php _e('Abstract:', 'dgptm-artikel-einreichung'); ?></label>
            <div class="abstract-text"><?php echo wp_kses_post(nl2br($abstract)); ?></div>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($current_version): ?>
    <div class="dgptm-reviewer-download">
        <h3><?php _e('Manuskript herunterladen', 'dgptm-artikel-einreichung'); ?></h3>
        <p class="description"><?php _e('Bitte laden Sie das Manuskript herunter und begutachten Sie es.', 'dgptm-artikel-einreichung'); ?></p>

        <a href="<?php echo esc_url(add_query_arg(array(
            'action' => 'dgptm_reviewer_download',
            'token' => $token,
            'source' => $current_version['source'],
            'version_id' => $current_version['id'],
            'nonce' => wp_create_nonce('dgptm_reviewer_nonce'),
        ), admin_url('admin-ajax.php'))); ?>" class="dgptm-download-button" target="_blank">
            <span class="dashicons dashicons-pdf"></span>
            <span class="filename"><?php echo esc_html($current_version['filename'] ?? __('Manuskript', 'dgptm-artikel-einreichung')); ?></span>
            <?php if (isset($current_version['version'])): ?>
            <span class="version">(V<?php echo esc_html($current_version['version']); ?>)</span>
            <?php endif; ?>
        </a>
    </div>
    <?php endif; ?>

    <div class="dgptm-reviewer-divider"></div>

    <div class="dgptm-reviewer-form-section">
        <h3><?php printf(__('Ihr %s hochladen', 'dgptm-artikel-einreichung'), esc_html($type_label)); ?></h3>

        <form id="dgptm-reviewer-upload-form" class="dgptm-reviewer-form" enctype="multipart/form-data">
            <input type="hidden" name="action" value="dgptm_reviewer_upload">
            <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('dgptm_reviewer_nonce'); ?>">

            <?php if (!empty($token_data['reviewer_name'])): ?>
                <input type="hidden" name="reviewer_name" value="<?php echo esc_attr($token_data['reviewer_name']); ?>">
                <input type="hidden" name="reviewer_email" value="<?php echo esc_attr($token_data['reviewer_email']); ?>">
                <div class="form-field form-field-readonly">
                    <label><?php _e('Ihr Name:', 'dgptm-artikel-einreichung'); ?></label>
                    <span class="field-value"><?php echo esc_html($token_data['reviewer_name']); ?></span>
                </div>
            <?php else: ?>
                <div class="form-field form-field-required">
                    <label for="reviewer_name"><?php _e('Ihr Name', 'dgptm-artikel-einreichung'); ?> <span class="required">*</span></label>
                    <input type="text" id="reviewer_name" name="reviewer_name" required placeholder="<?php esc_attr_e('Vorname Nachname', 'dgptm-artikel-einreichung'); ?>">
                </div>

                <div class="form-field form-field-required">
                    <label for="reviewer_email"><?php _e('Ihre E-Mail', 'dgptm-artikel-einreichung'); ?> <span class="required">*</span></label>
                    <input type="email" id="reviewer_email" name="reviewer_email" required placeholder="<?php esc_attr_e('ihre.email@beispiel.de', 'dgptm-artikel-einreichung'); ?>">
                </div>
            <?php endif; ?>

            <div class="form-field form-field-file form-field-required">
                <label for="reviewer_file"><?php _e('Datei', 'dgptm-artikel-einreichung'); ?> <span class="required">*</span></label>
                <div class="file-upload-wrapper">
                    <input type="file" id="reviewer_file" name="file" required accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
                    <div class="file-upload-info">
                        <span class="dashicons dashicons-upload"></span>
                        <span class="upload-text"><?php _e('Datei auswählen oder hierher ziehen', 'dgptm-artikel-einreichung'); ?></span>
                        <span class="file-name"></span>
                    </div>
                </div>
                <p class="description"><?php _e('PDF oder DOCX, max. 20 MB', 'dgptm-artikel-einreichung'); ?></p>
            </div>

            <div class="form-field form-field-textarea">
                <label for="reviewer_notes"><?php _e('Kommentar', 'dgptm-artikel-einreichung'); ?></label>
                <textarea id="reviewer_notes" name="notes" rows="4" placeholder="<?php esc_attr_e('Optionale Anmerkungen zu Ihrem Gutachten...', 'dgptm-artikel-einreichung'); ?>"></textarea>
            </div>

            <div class="form-field form-field-submit">
                <button type="submit" class="dgptm-submit-button">
                    <span class="button-text"><?php printf(__('%s absenden', 'dgptm-artikel-einreichung'), esc_html($type_label)); ?></span>
                    <span class="button-loading" style="display: none;">
                        <span class="spinner"></span>
                        <?php _e('Wird hochgeladen...', 'dgptm-artikel-einreichung'); ?>
                    </span>
                </button>
            </div>
        </form>
    </div>

    <div class="dgptm-reviewer-success" style="display: none;">
        <div class="success-icon">
            <span class="dashicons dashicons-yes-alt"></span>
        </div>
        <div class="success-content">
            <h3><?php _e('Vielen Dank!', 'dgptm-artikel-einreichung'); ?></h3>
            <p><?php printf(__('Ihr %s wurde erfolgreich hochgeladen.', 'dgptm-artikel-einreichung'), esc_html(strtolower($type_label))); ?></p>
            <p class="success-note"><?php _e('Die Redaktion wurde benachrichtigt und wird sich bei Bedarf bei Ihnen melden.', 'dgptm-artikel-einreichung'); ?></p>
        </div>
    </div>

    <div class="dgptm-reviewer-footer">
        <p class="dgptm-privacy-note">
            <?php _e('Ihre Daten werden gemäß unserer Datenschutzrichtlinie verarbeitet.', 'dgptm-artikel-einreichung'); ?>
        </p>
    </div>
</div>
