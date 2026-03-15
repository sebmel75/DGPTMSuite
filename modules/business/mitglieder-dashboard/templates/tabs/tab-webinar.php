<?php
if (!defined('ABSPATH')) exit;
?>
<h3 class="dgptm-section-title">Webinar-Verwaltung</h3>
<?php if (shortcode_exists('vimeo_webinar_manager')) : ?>
    <?php echo do_shortcode('[vimeo_webinar_manager]'); ?>
<?php else : ?>
    <div class="dgptm-tab-unavailable">
        <span class="dashicons dashicons-video-alt3"></span>
        <p>Webinar-Modul ist derzeit nicht verfuegbar.</p>
    </div>
<?php endif; ?>
