<?php
/**
 * Template: Admin Einstellungen
 * Version: 1.3.0
 */

if (!defined('ABSPATH')) exit;

$orientation = $settings['orientation'] ?? 'L';
$bg_id = $settings['background_image'] ?? 0;
$logo_id = $settings['logo_image'] ?? 0;
$watermark_id = $settings['watermark_image'] ?? 0;
$watermark_opacity = $settings['watermark_opacity'] ?? 30;
$watermark_position = $settings['watermark_position'] ?? 'center';
$header_text = $settings['header_text'] ?? 'Teilnahmebescheinigung';
$footer_text = $settings['footer_text'] ?? get_bloginfo('name');
$signature_text = $settings['signature_text'] ?? '';
$mail_enabled = $settings['mail_enabled'] ?? true;
$mail_subject = $settings['mail_subject'] ?? 'Ihr Webinar-Zertifikat: {webinar_title}';
$mail_body = $settings['mail_body'] ?? "Hallo {user_name},\n\nvielen Dank für Ihre Teilnahme am Webinar \"{webinar_title}\".\n\nIhr Teilnahmezertifikat steht zum Download bereit:\n{certificate_url}\n\nMit freundlichen Grüßen\nIhr Webinar-Team";
$mail_from = $settings['mail_from'] ?? get_option('admin_email');
$mail_from_name = $settings['mail_from_name'] ?? get_bloginfo('name');

// Webinar-Seite Option
$webinar_page_id = get_option('vw_webinar_page_id', 0);
?>

<div class="wrap">
    <h1>Webinar Einstellungen</h1>

    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('vw_settings_nonce', 'vw_settings_nonce'); ?>

        <h2>Allgemeine Einstellungen</h2>
        <table class="form-table">
            <tr>
                <th scope="row">Webinar-Seite</th>
                <td>
                    <?php
                    wp_dropdown_pages([
                        'name' => 'webinar_page_id',
                        'id' => 'webinar_page_id',
                        'selected' => $webinar_page_id,
                        'show_option_none' => '— Automatisch (dynamische URL) —',
                        'option_none_value' => 0,
                    ]);
                    ?>
                    <p class="description">
                        <strong>Option 1 - Automatisch:</strong> Webinare sind unter <code>/wissen/webinar/{ID}</code> erreichbar (Plugin rendert die Seite).<br>
                        <strong>Option 2 - Eigene Seite:</strong> Wählen Sie eine WordPress-Seite mit dem Shortcode <code>[vimeo_webinar]</code>. 
                        Webinare sind dann über <code>?id={ID}</code> oder <code>?webinar={ID}</code> erreichbar.<br>
                        <em>Beispiel: Seite "Webinar" → URL wird <code>/webinar/?id=123</code></em>
                    </p>
                </td>
            </tr>
        </table>

        <h2>Zertifikat-Template</h2>
        <p class="description">Für einen visuellen Zertifikat-Editor nutzen Sie den <a href="<?php echo admin_url('edit.php?post_type=vimeo_webinar&page=vimeo-webinar-certificate'); ?>">Zertifikat Designer</a>.</p>
        
        <table class="form-table">
            <tr>
                <th scope="row">Ausrichtung</th>
                <td>
                    <select name="orientation">
                        <option value="L" <?php selected($orientation, 'L'); ?>>Querformat (Landscape)</option>
                        <option value="P" <?php selected($orientation, 'P'); ?>>Hochformat (Portrait)</option>
                    </select>
                </td>
            </tr>

            <tr>
                <th scope="row">Hintergrundbild</th>
                <td>
                    <input type="hidden" name="background_image" id="background_image" value="<?php echo esc_attr($bg_id); ?>">
                    <button type="button" class="button vw-upload-image" data-target="background_image">Bild hochladen</button>
                    <button type="button" class="button vw-remove-image" data-target="background_image">Entfernen</button>
                    <div id="background_image_preview" style="margin-top: 10px;">
                        <?php if ($bg_id): ?>
                            <img src="<?php echo esc_url(wp_get_attachment_url($bg_id)); ?>" style="max-width: 300px; height: auto;">
                        <?php endif; ?>
                    </div>
                    <p class="description">Empfohlene Größe: 297x210mm (Querformat) oder 210x297mm (Hochformat) bei 300 DPI</p>
                </td>
            </tr>

            <tr>
                <th scope="row">Logo</th>
                <td>
                    <input type="hidden" name="logo_image" id="logo_image" value="<?php echo esc_attr($logo_id); ?>">
                    <button type="button" class="button vw-upload-image" data-target="logo_image">Logo hochladen</button>
                    <button type="button" class="button vw-remove-image" data-target="logo_image">Entfernen</button>
                    <div id="logo_image_preview" style="margin-top: 10px;">
                        <?php if ($logo_id): ?>
                            <img src="<?php echo esc_url(wp_get_attachment_url($logo_id)); ?>" style="max-width: 200px; height: auto;">
                        <?php endif; ?>
                    </div>
                    <p class="description">Wird oben links platziert (40mm Breite)</p>
                </td>
            </tr>

            <tr>
                <th scope="row">Wasserzeichen</th>
                <td>
                    <input type="hidden" name="watermark_image" id="watermark_image" value="<?php echo esc_attr($watermark_id); ?>">
                    <button type="button" class="button vw-upload-image" data-target="watermark_image">Wasserzeichen hochladen</button>
                    <button type="button" class="button vw-remove-image" data-target="watermark_image">Entfernen</button>
                    <div id="watermark_image_preview" style="margin-top: 10px;">
                        <?php if ($watermark_id): ?>
                            <img src="<?php echo esc_url(wp_get_attachment_url($watermark_id)); ?>" style="max-width: 200px; height: auto;">
                        <?php endif; ?>
                    </div>
                    <p class="description">PNG mit Transparenz empfohlen. Kann auch pro Webinar überschrieben werden.</p>
                </td>
            </tr>

            <tr>
                <th scope="row">Wasserzeichen Position</th>
                <td>
                    <select name="watermark_position">
                        <option value="center" <?php selected($watermark_position, 'center'); ?>>Mittig</option>
                        <option value="top-left" <?php selected($watermark_position, 'top-left'); ?>>Oben links</option>
                        <option value="top-right" <?php selected($watermark_position, 'top-right'); ?>>Oben rechts</option>
                        <option value="bottom-left" <?php selected($watermark_position, 'bottom-left'); ?>>Unten links</option>
                        <option value="bottom-right" <?php selected($watermark_position, 'bottom-right'); ?>>Unten rechts</option>
                    </select>
                </td>
            </tr>

            <tr>
                <th scope="row">Wasserzeichen Transparenz</th>
                <td>
                    <input type="number" name="watermark_opacity" value="<?php echo esc_attr($watermark_opacity); ?>" min="10" max="100" step="5"> %
                    <p class="description">Niedrigere Werte = transparenter (10-100%)</p>
                </td>
            </tr>

            <tr>
                <th scope="row">Kopfzeile</th>
                <td>
                    <input type="text" name="header_text" value="<?php echo esc_attr($header_text); ?>" class="regular-text">
                    <p class="description">Hauptüberschrift des Zertifikats</p>
                </td>
            </tr>

            <tr>
                <th scope="row">Fußzeile</th>
                <td>
                    <input type="text" name="footer_text" value="<?php echo esc_attr($footer_text); ?>" class="regular-text">
                    <p class="description">Text am unteren Rand</p>
                </td>
            </tr>

            <tr>
                <th scope="row">Unterschrift/Bestätigung</th>
                <td>
                    <input type="text" name="signature_text" value="<?php echo esc_attr($signature_text); ?>" class="regular-text">
                    <p class="description">z.B. "Unterschrift", "Veranstalter", etc.</p>
                </td>
            </tr>
        </table>

        <h2>E-Mail-Benachrichtigungen</h2>
        <table class="form-table">
            <tr>
                <th scope="row">E-Mail aktiviert</th>
                <td>
                    <label>
                        <input type="checkbox" name="mail_enabled" value="1" <?php checked($mail_enabled, true); ?>>
                        Zertifikat automatisch per E-Mail verschicken
                    </label>
                    <p class="description">Sendet das Zertifikat nach Abschluss automatisch an den Benutzer</p>
                </td>
            </tr>

            <tr>
                <th scope="row">Absender-Name</th>
                <td>
                    <input type="text" name="mail_from_name" value="<?php echo esc_attr($mail_from_name); ?>" class="regular-text">
                    <p class="description">Name des Absenders (z.B. Ihr Organisationsname)</p>
                </td>
            </tr>

            <tr>
                <th scope="row">Absender-E-Mail</th>
                <td>
                    <input type="email" name="mail_from" value="<?php echo esc_attr($mail_from); ?>" class="regular-text">
                    <p class="description">E-Mail-Adresse des Absenders</p>
                </td>
            </tr>

            <tr>
                <th scope="row">E-Mail-Betreff</th>
                <td>
                    <input type="text" name="mail_subject" value="<?php echo esc_attr($mail_subject); ?>" class="large-text">
                    <p class="description">
                        Verfügbare Platzhalter:
                        <code>{user_name}</code>,
                        <code>{user_first_name}</code>,
                        <code>{user_last_name}</code>,
                        <code>{webinar_title}</code>,
                        <code>{points}</code>,
                        <code>{date}</code>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">E-Mail-Text</th>
                <td>
                    <textarea name="mail_body" rows="10" class="large-text code"><?php echo esc_textarea($mail_body); ?></textarea>
                    <p class="description">
                        Verfügbare Platzhalter:
                        <code>{user_name}</code>,
                        <code>{user_first_name}</code>,
                        <code>{user_last_name}</code>,
                        <code>{user_email}</code>,
                        <code>{webinar_title}</code>,
                        <code>{webinar_url}</code>,
                        <code>{certificate_url}</code>,
                        <code>{points}</code>,
                        <code>{date}</code>
                    </p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" name="vw_save_settings" class="button button-primary" value="Einstellungen speichern">
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    var mediaUploader;

    $('.vw-upload-image').on('click', function(e) {
        e.preventDefault();
        var button = $(this);
        var targetId = button.data('target');

        if (mediaUploader) {
            mediaUploader.open();
            return;
        }

        mediaUploader = wp.media({
            title: 'Bild auswählen',
            button: {
                text: 'Bild verwenden'
            },
            multiple: false
        });

        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#' + targetId).val(attachment.id);
            $('#' + targetId + '_preview').html('<img src="' + attachment.url + '" style="max-width: 300px; height: auto;">');
        });

        mediaUploader.open();
    });

    $('.vw-remove-image').on('click', function(e) {
        e.preventDefault();
        var targetId = $(this).data('target');
        $('#' + targetId).val('');
        $('#' + targetId + '_preview').html('');
    });
});
</script>
