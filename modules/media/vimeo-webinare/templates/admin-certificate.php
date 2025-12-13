<?php
/**
 * Template: Zertifikat Designer (Admin)
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
?>

<div class="wrap vw-certificate-designer">
    <h1>🎨 Zertifikat Designer</h1>
    <p class="description">Gestalten Sie das Aussehen der Teilnahmezertifikate für Ihre Webinare.</p>

    <div class="vw-designer-container">
        <!-- Preview Section -->
        <div class="vw-designer-preview">
            <h2>Vorschau</h2>
            <div class="vw-preview-frame <?php echo $orientation === 'L' ? 'landscape' : 'portrait'; ?>">
                <div class="vw-preview-content" id="certificate-preview">
                    <?php if ($bg_id): ?>
                        <img class="vw-preview-bg" src="<?php echo esc_url(wp_get_attachment_url($bg_id)); ?>" alt="Hintergrund">
                    <?php endif; ?>
                    
                    <?php if ($watermark_id): ?>
                        <img class="vw-preview-watermark position-<?php echo esc_attr($watermark_position); ?>" 
                             src="<?php echo esc_url(wp_get_attachment_url($watermark_id)); ?>" 
                             alt="Wasserzeichen"
                             style="opacity: <?php echo esc_attr($watermark_opacity / 100); ?>">
                    <?php endif; ?>
                    
                    <?php if ($logo_id): ?>
                        <img class="vw-preview-logo" src="<?php echo esc_url(wp_get_attachment_url($logo_id)); ?>" alt="Logo">
                    <?php endif; ?>
                    
                    <div class="vw-preview-header"><?php echo esc_html($header_text); ?></div>
                    <div class="vw-preview-title">[ Webinar-Titel ]</div>
                    <div class="vw-preview-name">[ Teilnehmer-Name ]</div>
                    <div class="vw-preview-text">hat erfolgreich am o.g. Webinar teilgenommen</div>
                    <div class="vw-preview-points">Fortbildungspunkte: X EBCP</div>
                    <div class="vw-preview-date">Datum: <?php echo current_time('d.m.Y'); ?></div>
                    <?php if ($signature_text): ?>
                        <div class="vw-preview-signature"><?php echo esc_html($signature_text); ?></div>
                    <?php endif; ?>
                    <div class="vw-preview-footer"><?php echo esc_html($footer_text); ?></div>
                </div>
            </div>
            
            <div class="vw-preview-actions">
                <button type="button" class="button button-secondary" id="vw-generate-preview">
                    📄 PDF Vorschau generieren
                </button>
            </div>
        </div>

        <!-- Settings Form -->
        <div class="vw-designer-settings">
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('vw_certificate_nonce', 'vw_certificate_nonce'); ?>

                <!-- Layout Section -->
                <div class="vw-settings-section">
                    <h3>📐 Layout</h3>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Ausrichtung</th>
                            <td>
                                <select name="orientation" id="vw-orientation">
                                    <option value="L" <?php selected($orientation, 'L'); ?>>Querformat (Landscape)</option>
                                    <option value="P" <?php selected($orientation, 'P'); ?>>Hochformat (Portrait)</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Images Section -->
                <div class="vw-settings-section">
                    <h3>🖼️ Bilder</h3>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Hintergrundbild</th>
                            <td>
                                <input type="hidden" name="background_image" id="background_image" value="<?php echo esc_attr($bg_id); ?>">
                                <div class="vw-image-upload">
                                    <button type="button" class="button vw-upload-image" data-target="background_image">Bild auswählen</button>
                                    <button type="button" class="button vw-remove-image" data-target="background_image">Entfernen</button>
                                </div>
                                <div id="background_image_preview" class="vw-image-preview">
                                    <?php if ($bg_id): ?>
                                        <img src="<?php echo esc_url(wp_get_attachment_url($bg_id)); ?>">
                                    <?php endif; ?>
                                </div>
                                <p class="description">Empfohlen: 297×210mm (Querformat) oder 210×297mm (Hochformat) bei 300 DPI</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">Logo</th>
                            <td>
                                <input type="hidden" name="logo_image" id="logo_image" value="<?php echo esc_attr($logo_id); ?>">
                                <div class="vw-image-upload">
                                    <button type="button" class="button vw-upload-image" data-target="logo_image">Logo auswählen</button>
                                    <button type="button" class="button vw-remove-image" data-target="logo_image">Entfernen</button>
                                </div>
                                <div id="logo_image_preview" class="vw-image-preview">
                                    <?php if ($logo_id): ?>
                                        <img src="<?php echo esc_url(wp_get_attachment_url($logo_id)); ?>">
                                    <?php endif; ?>
                                </div>
                                <p class="description">Wird oben links platziert (40mm Breite)</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Watermark Section -->
                <div class="vw-settings-section">
                    <h3>💧 Wasserzeichen</h3>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Wasserzeichen-Bild</th>
                            <td>
                                <input type="hidden" name="watermark_image" id="watermark_image" value="<?php echo esc_attr($watermark_id); ?>">
                                <div class="vw-image-upload">
                                    <button type="button" class="button vw-upload-image" data-target="watermark_image">Bild auswählen</button>
                                    <button type="button" class="button vw-remove-image" data-target="watermark_image">Entfernen</button>
                                </div>
                                <div id="watermark_image_preview" class="vw-image-preview">
                                    <?php if ($watermark_id): ?>
                                        <img src="<?php echo esc_url(wp_get_attachment_url($watermark_id)); ?>">
                                    <?php endif; ?>
                                </div>
                                <p class="description">PNG mit Transparenz empfohlen. Kann auch pro Webinar überschrieben werden.</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">Position</th>
                            <td>
                                <select name="watermark_position" id="vw-watermark-position">
                                    <option value="center" <?php selected($watermark_position, 'center'); ?>>Mittig</option>
                                    <option value="top-left" <?php selected($watermark_position, 'top-left'); ?>>Oben links</option>
                                    <option value="top-right" <?php selected($watermark_position, 'top-right'); ?>>Oben rechts</option>
                                    <option value="bottom-left" <?php selected($watermark_position, 'bottom-left'); ?>>Unten links</option>
                                    <option value="bottom-right" <?php selected($watermark_position, 'bottom-right'); ?>>Unten rechts</option>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">Transparenz</th>
                            <td>
                                <input type="range" name="watermark_opacity" id="vw-watermark-opacity" 
                                       min="10" max="100" value="<?php echo esc_attr($watermark_opacity); ?>">
                                <span id="vw-opacity-value"><?php echo esc_html($watermark_opacity); ?>%</span>
                                <p class="description">Niedrigere Werte = transparenter</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Text Section -->
                <div class="vw-settings-section">
                    <h3>📝 Texte</h3>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Kopfzeile</th>
                            <td>
                                <input type="text" name="header_text" id="vw-header-text" 
                                       value="<?php echo esc_attr($header_text); ?>" class="regular-text">
                                <p class="description">Hauptüberschrift des Zertifikats</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">Fußzeile</th>
                            <td>
                                <input type="text" name="footer_text" id="vw-footer-text" 
                                       value="<?php echo esc_attr($footer_text); ?>" class="regular-text">
                                <p class="description">Text am unteren Rand</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">Unterschrift/Bestätigung</th>
                            <td>
                                <input type="text" name="signature_text" id="vw-signature-text" 
                                       value="<?php echo esc_attr($signature_text); ?>" class="regular-text">
                                <p class="description">z.B. "Unterschrift", "Veranstalter", etc.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <p class="submit">
                    <input type="submit" name="vw_save_certificate" class="button button-primary" value="Einstellungen speichern">
                </p>
            </form>
        </div>
    </div>
</div>

<style>
.vw-certificate-designer { max-width: 1400px; }
.vw-designer-container { display: flex; gap: 30px; flex-wrap: wrap; }
.vw-designer-preview { flex: 1; min-width: 400px; }
.vw-designer-settings { flex: 1; min-width: 400px; }

.vw-preview-frame {
    background: #f5f5f5;
    border: 2px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    justify-content: center;
    align-items: center;
}
.vw-preview-frame.landscape .vw-preview-content { width: 100%; aspect-ratio: 297/210; }
.vw-preview-frame.portrait .vw-preview-content { width: 70%; aspect-ratio: 210/297; }

.vw-preview-content {
    background: white;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    position: relative;
    overflow: hidden;
    padding: 5%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.vw-preview-bg {
    position: absolute;
    top: 0; left: 0;
    width: 100%; height: 100%;
    object-fit: cover;
    z-index: 0;
}

.vw-preview-watermark {
    position: absolute;
    width: 50%;
    max-height: 50%;
    object-fit: contain;
    z-index: 1;
}
.vw-preview-watermark.position-center { top: 50%; left: 50%; transform: translate(-50%, -50%); }
.vw-preview-watermark.position-top-left { top: 10%; left: 10%; transform: none; }
.vw-preview-watermark.position-top-right { top: 10%; right: 10%; transform: none; left: auto; }
.vw-preview-watermark.position-bottom-left { bottom: 10%; left: 10%; transform: none; top: auto; }
.vw-preview-watermark.position-bottom-right { bottom: 10%; right: 10%; transform: none; top: auto; left: auto; }

.vw-preview-logo {
    position: absolute;
    top: 5%; left: 5%;
    width: 15%;
    z-index: 2;
}

.vw-preview-header { font-size: 1.5em; font-weight: bold; margin-bottom: 10%; z-index: 2; }
.vw-preview-title { font-size: 1.2em; font-weight: bold; margin-bottom: 5%; z-index: 2; }
.vw-preview-name { font-size: 1.1em; font-weight: bold; margin-bottom: 5%; z-index: 2; }
.vw-preview-text, .vw-preview-points, .vw-preview-date { font-size: 0.9em; margin-bottom: 3%; z-index: 2; }
.vw-preview-signature { font-size: 0.8em; font-style: italic; margin-top: 5%; z-index: 2; }
.vw-preview-footer { position: absolute; bottom: 3%; font-size: 0.7em; z-index: 2; }

.vw-preview-actions { margin-top: 20px; text-align: center; }

.vw-settings-section {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}
.vw-settings-section h3 { margin-top: 0; }

.vw-image-upload { margin-bottom: 10px; }
.vw-image-preview img { max-width: 200px; height: auto; margin-top: 10px; border: 1px solid #ddd; }

#vw-watermark-opacity { width: 200px; vertical-align: middle; }
#vw-opacity-value { margin-left: 10px; }
</style>

<script>
jQuery(document).ready(function($) {
    var mediaUploader;

    // Image Upload
    $('.vw-upload-image').on('click', function(e) {
        e.preventDefault();
        var button = $(this);
        var targetId = button.data('target');

        mediaUploader = wp.media({
            title: 'Bild auswählen',
            button: { text: 'Bild verwenden' },
            multiple: false
        });

        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#' + targetId).val(attachment.id);
            $('#' + targetId + '_preview').html('<img src="' + attachment.url + '">');
            updatePreview();
        });

        mediaUploader.open();
    });

    // Remove Image
    $('.vw-remove-image').on('click', function(e) {
        e.preventDefault();
        var targetId = $(this).data('target');
        $('#' + targetId).val('');
        $('#' + targetId + '_preview').html('');
        updatePreview();
    });

    // Opacity Slider
    $('#vw-watermark-opacity').on('input', function() {
        var value = $(this).val();
        $('#vw-opacity-value').text(value + '%');
        $('.vw-preview-watermark').css('opacity', value / 100);
    });

    // Watermark Position
    $('#vw-watermark-position').on('change', function() {
        var position = $(this).val();
        $('.vw-preview-watermark').removeClass('position-center position-top-left position-top-right position-bottom-left position-bottom-right')
                                   .addClass('position-' + position);
    });

    // Text Updates
    $('#vw-header-text').on('input', function() {
        $('.vw-preview-header').text($(this).val() || 'Teilnahmebescheinigung');
    });
    $('#vw-footer-text').on('input', function() {
        $('.vw-preview-footer').text($(this).val());
    });
    $('#vw-signature-text').on('input', function() {
        var text = $(this).val();
        if (text) {
            $('.vw-preview-signature').text(text).show();
        } else {
            $('.vw-preview-signature').hide();
        }
    });

    // Orientation
    $('#vw-orientation').on('change', function() {
        var orientation = $(this).val();
        $('.vw-preview-frame').removeClass('landscape portrait').addClass(orientation === 'L' ? 'landscape' : 'portrait');
    });

    // Generate Preview PDF
    $('#vw-generate-preview').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Generiere...');

        $.ajax({
            url: vwCertData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'vw_preview_certificate',
                nonce: vwCertData.nonce
            },
            success: function(response) {
                if (response.success) {
                    window.open(response.data.pdf_url, '_blank');
                } else {
                    alert('Fehler: ' + (response.data.message || 'Unbekannter Fehler'));
                }
            },
            error: function() {
                alert('Netzwerkfehler');
            },
            complete: function() {
                $btn.prop('disabled', false).text('📄 PDF Vorschau generieren');
            }
        });
    });

    function updatePreview() {
        // Kann erweitert werden für Live-Preview
    }
});
</script>
