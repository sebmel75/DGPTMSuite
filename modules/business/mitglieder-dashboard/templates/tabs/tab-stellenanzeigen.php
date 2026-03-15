<?php
if (!defined('ABSPATH')) exit;
?>
<h3 class="dgptm-section-title">Stellenanzeigen verwalten</h3>
<?php if (shortcode_exists('stellenanzeigen_editor')) : ?>
    <?php echo do_shortcode('[stellenanzeigen_editor]'); ?>
<?php else : ?>
    <div class="dgptm-tab-unavailable">
        <span class="dashicons dashicons-businessperson"></span>
        <p>Stellenanzeigen-Modul ist derzeit nicht verfuegbar.</p>
    </div>
<?php endif; ?>
