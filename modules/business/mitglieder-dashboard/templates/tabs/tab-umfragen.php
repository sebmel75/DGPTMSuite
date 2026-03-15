<?php
if (!defined('ABSPATH')) exit;
?>
<h3 class="dgptm-section-title">Umfragen</h3>
<?php if (shortcode_exists('dgptm_umfrage_editor')) : ?>
    <?php echo do_shortcode('[dgptm_umfrage_editor]'); ?>
<?php else : ?>
    <div class="dgptm-tab-unavailable">
        <span class="dashicons dashicons-forms"></span>
        <p>Umfragen-Modul ist derzeit nicht verfuegbar.</p>
    </div>
<?php endif; ?>
