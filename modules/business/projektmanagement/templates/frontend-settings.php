<?php
/**
 * Template: Email Settings Management
 * Variables: $email_templates, $placeholders
 */
if (!defined('ABSPATH')) {
    exit;
}

$template_labels = [
    'task_assigned' => 'Aufgabe zugewiesen',
    'task_completed' => 'Aufgabe abgeschlossen',
    'comment_added' => 'Neuer Kommentar',
    'daily_summary' => 'Taegliche Zusammenfassung',
];
?>

<div class="pm-container pm-settings">

    <div class="pm-header">
        <h2>Projektmanagement Einstellungen</h2>
    </div>

    <div class="pm-settings-section">
        <h3>E-Mail-Vorlagen</h3>
        <p class="pm-hint">Passen Sie die E-Mail-Benachrichtigungen an. Verwenden Sie die Platzhalter, um dynamische Inhalte einzufuegen.</p>

        <form id="pm-email-templates-form">
            <div class="pm-email-templates-list">
                <?php foreach ($email_templates as $key => $template): ?>
                <div class="pm-email-template-card" data-template="<?php echo esc_attr($key); ?>">
                    <div class="pm-template-header">
                        <h4><?php echo esc_html($template_labels[$key] ?? $key); ?></h4>
                        <label class="pm-toggle">
                            <input type="checkbox" name="templates[<?php echo esc_attr($key); ?>][enabled]" value="1" <?php checked($template['enabled']); ?>>
                            <span class="pm-toggle-slider"></span>
                            <span class="pm-toggle-label">Aktiviert</span>
                        </label>
                    </div>

                    <div class="pm-template-body">
                        <div class="pm-form-group">
                            <label>Betreff</label>
                            <input type="text" name="templates[<?php echo esc_attr($key); ?>][subject]"
                                   value="<?php echo esc_attr($template['subject']); ?>" class="pm-template-subject">
                        </div>

                        <div class="pm-form-group">
                            <label>Inhalt (HTML)</label>
                            <textarea name="templates[<?php echo esc_attr($key); ?>][body]" rows="10"
                                      class="pm-template-body-input"><?php echo esc_textarea($template['body']); ?></textarea>
                        </div>

                        <?php if (isset($placeholders[$key])): ?>
                        <div class="pm-placeholders">
                            <strong>Verfuegbare Platzhalter:</strong>
                            <div class="pm-placeholder-list">
                                <?php foreach ($placeholders[$key] as $placeholder => $description): ?>
                                <span class="pm-placeholder" title="<?php echo esc_attr($description); ?>"
                                      data-placeholder="<?php echo esc_attr($placeholder); ?>">
                                    <?php echo esc_html($placeholder); ?>
                                </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="pm-form-actions">
                <button type="button" class="pm-btn pm-btn-secondary" id="pm-reset-templates">
                    Auf Standard zuruecksetzen
                </button>
                <button type="submit" class="pm-btn pm-btn-primary">
                    Vorlagen speichern
                </button>
            </div>
        </form>
    </div>

</div>

<script>
jQuery(document).ready(function($) {
    // Save email templates
    $('#pm-email-templates-form').on('submit', function(e) {
        e.preventDefault();

        var $btn = $(this).find('button[type="submit"]');
        $btn.prop('disabled', true).text('Speichern...');

        var templates = {};
        $('.pm-email-template-card').each(function() {
            var key = $(this).data('template');
            templates[key] = {
                subject: $(this).find('.pm-template-subject').val(),
                body: $(this).find('.pm-template-body-input').val(),
                enabled: $(this).find('input[type="checkbox"]').is(':checked') ? '1' : ''
            };
        });

        $.ajax({
            url: pmData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pm_save_email_templates',
                nonce: pmData.nonce,
                templates: templates
            },
            success: function(response) {
                if (response.success) {
                    alert('E-Mail-Vorlagen gespeichert!');
                } else {
                    alert(response.data.message || 'Fehler beim Speichern');
                }
                $btn.prop('disabled', false).text('Vorlagen speichern');
            },
            error: function() {
                alert('Verbindungsfehler');
                $btn.prop('disabled', false).text('Vorlagen speichern');
            }
        });
    });

    // Reset templates
    $('#pm-reset-templates').on('click', function() {
        if (!confirm('Moechten Sie alle E-Mail-Vorlagen auf die Standardwerte zuruecksetzen?')) {
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text('Zuruecksetzen...');

        $.ajax({
            url: pmData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pm_reset_email_templates',
                nonce: pmData.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('E-Mail-Vorlagen zurueckgesetzt!');
                    location.reload();
                } else {
                    alert(response.data.message || 'Fehler');
                    $btn.prop('disabled', false).text('Auf Standard zuruecksetzen');
                }
            },
            error: function() {
                alert('Verbindungsfehler');
                $btn.prop('disabled', false).text('Auf Standard zuruecksetzen');
            }
        });
    });

    // Insert placeholder on click
    $('.pm-placeholder').on('click', function() {
        var placeholder = $(this).data('placeholder');
        var $card = $(this).closest('.pm-email-template-card');
        var $textarea = $card.find('.pm-template-body-input');
        var cursorPos = $textarea[0].selectionStart;
        var textBefore = $textarea.val().substring(0, cursorPos);
        var textAfter = $textarea.val().substring(cursorPos);
        $textarea.val(textBefore + placeholder + textAfter);
        $textarea.focus();
    });
});
</script>

<?php include __DIR__ . '/partials/modals.php'; ?>
