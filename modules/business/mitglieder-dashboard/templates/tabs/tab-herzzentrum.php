<?php
if (!defined('ABSPATH')) exit;
?>
<h3 class="dgptm-section-title">Herzzentrum bearbeiten</h3>
<?php if (shortcode_exists('hzb_edit_form_link')) : ?>
    <?php echo do_shortcode('[hzb_edit_form_link]'); ?>
<?php else : ?>
    <div class="dgptm-tab-unavailable">
        <span class="dashicons dashicons-heart"></span>
        <p>Herzzentren-Modul ist derzeit nicht verfuegbar.</p>
    </div>
<?php endif; ?>
