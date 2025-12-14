<?php
/**
 * Admin Template: Einstellungen
 * Allgemeine Einstellungen für das Artikel-Einreichungssystem
 */

if (!defined('ABSPATH')) exit;

$plugin = DGPTM_Artikel_Einreichung::get_instance();

// Handle form submission
if (isset($_POST['dgptm_artikel_settings_submit']) && wp_verify_nonce($_POST['_wpnonce'], 'dgptm_artikel_settings')) {
    $settings = [
        'email_notifications' => isset($_POST['email_notifications']) ? 1 : 0,
        'notification_email' => sanitize_email($_POST['notification_email'] ?? ''),
        'submission_confirmation_text' => wp_kses_post($_POST['submission_confirmation_text'] ?? ''),
        'review_instructions' => wp_kses_post($_POST['review_instructions'] ?? ''),
        'max_file_size' => intval($_POST['max_file_size'] ?? 20),
        'auto_assign_reviewers' => isset($_POST['auto_assign_reviewers']) ? 1 : 0
    ];

    update_option(DGPTM_Artikel_Einreichung::OPT_SETTINGS, $settings);
    echo '<div class="notice notice-success"><p>Einstellungen gespeichert.</p></div>';
}

// Get current settings
$settings = get_option(DGPTM_Artikel_Einreichung::OPT_SETTINGS, [
    'email_notifications' => 1,
    'notification_email' => get_option('admin_email'),
    'submission_confirmation_text' => '',
    'review_instructions' => '',
    'max_file_size' => 20,
    'auto_assign_reviewers' => 0
]);
?>

<div class="wrap dgptm-artikel-admin">
    <h1>Einstellungen - Artikel-Einreichung</h1>

    <form method="post" action="">
        <?php wp_nonce_field('dgptm_artikel_settings'); ?>

        <div class="dgptm-admin-box">
            <h2>E-Mail-Benachrichtigungen</h2>

            <table class="form-table">
                <tr>
                    <th scope="row">E-Mail-Benachrichtigungen aktiv</th>
                    <td>
                        <label>
                            <input type="checkbox" name="email_notifications" value="1" <?php checked($settings['email_notifications'], 1); ?>>
                            E-Mail-Benachrichtigungen bei Statusänderungen senden
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Zusätzliche Benachrichtigungs-E-Mail</th>
                    <td>
                        <input type="email" name="notification_email" value="<?php echo esc_attr($settings['notification_email']); ?>" class="regular-text">
                        <p class="description">
                            Optional: Zusätzliche E-Mail-Adresse für Benachrichtigungen (zusätzlich zum Editor in Chief).
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="dgptm-admin-box">
            <h2>Texte</h2>

            <table class="form-table">
                <tr>
                    <th scope="row">Bestätigungstext nach Einreichung</th>
                    <td>
                        <textarea name="submission_confirmation_text" rows="5" class="large-text"><?php echo esc_textarea($settings['submission_confirmation_text']); ?></textarea>
                        <p class="description">
                            Optionaler zusätzlicher Text, der dem Autor nach erfolgreicher Einreichung angezeigt wird.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Review-Anweisungen</th>
                    <td>
                        <textarea name="review_instructions" rows="8" class="large-text"><?php echo esc_textarea($settings['review_instructions']); ?></textarea>
                        <p class="description">
                            Anweisungen für Reviewer. Wird auf der Review-Seite angezeigt.
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="dgptm-admin-box">
            <h2>Datei-Upload</h2>

            <table class="form-table">
                <tr>
                    <th scope="row">Maximale Dateigröße (MB)</th>
                    <td>
                        <input type="number" name="max_file_size" value="<?php echo esc_attr($settings['max_file_size']); ?>" min="1" max="100" class="small-text">
                        <p class="description">
                            Maximale Dateigröße für hochgeladene Dateien in Megabyte.
                            Server-Limit: <?php echo esc_html(ini_get('upload_max_filesize')); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="dgptm-admin-box">
            <h2>Shortcodes</h2>

            <p>Verwenden Sie diese Shortcodes auf Ihren Seiten:</p>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Shortcode</th>
                        <th>Beschreibung</th>
                        <th>Berechtigung</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>[artikel_einreichung]</code></td>
                        <td>Einreichungsformular für neue Artikel</td>
                        <td>Alle eingeloggten Benutzer</td>
                    </tr>
                    <tr>
                        <td><code>[artikel_dashboard]</code></td>
                        <td>Autoren-Dashboard - Übersicht der eigenen Einreichungen</td>
                        <td>Alle eingeloggten Benutzer (sehen nur eigene)</td>
                    </tr>
                    <tr>
                        <td><code>[artikel_review]</code></td>
                        <td>Reviewer-Dashboard - Zugewiesene Artikel begutachten</td>
                        <td>Zugewiesene Reviewer</td>
                    </tr>
                    <tr>
                        <td><code>[artikel_redaktion]</code></td>
                        <td>Redaktions-Übersicht - Alle Artikel (anonymisiert)</td>
                        <td>Benutzer mit ACF-Feld <code>redaktion_perfusiologie</code></td>
                    </tr>
                    <tr>
                        <td><code>[artikel_editor_dashboard]</code></td>
                        <td>Editor-in-Chief Dashboard - Vollzugriff im Frontend</td>
                        <td>Benutzer mit ACF-Feld <code>editor_in_chief</code></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="dgptm-admin-box">
            <h2>Berechtigungen</h2>

            <p>Die Berechtigungen werden über ACF-Benutzerfelder gesteuert:</p>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ACF-Feld</th>
                        <th>Berechtigung</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>editor_in_chief</code></td>
                        <td>Editor-in-Chief: Vollzugriff auf alle Funktionen, kann Reviewer zuweisen und Entscheidungen treffen</td>
                    </tr>
                    <tr>
                        <td><code>redaktion_perfusiologie</code></td>
                        <td>Redaktion: Kann alle Artikel einsehen (Status), aber keine Reviewer-Namen sehen und keine Zuweisungen vornehmen</td>
                    </tr>
                </tbody>
            </table>

            <p class="description" style="margin-top: 15px;">
                Diese Felder müssen in ACF unter "Benutzer" angelegt werden (True/False-Felder).
            </p>
        </div>

        <p class="submit">
            <input type="submit" name="dgptm_artikel_settings_submit" class="button button-primary" value="Einstellungen speichern">
        </p>
    </form>
</div>
