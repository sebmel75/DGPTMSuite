<?php
if (!defined('ABSPATH')) exit;
?>
<h3 class="dgptm-section-title">Checklisten</h3>
<?php if (shortcode_exists('clm_active_checklists')) : ?>
    <?php echo do_shortcode('[clm_active_checklists]'); ?>
<?php else : ?>
    <div class="dgptm-tab-unavailable">
        <span class="dashicons dashicons-yes"></span>
        <p>Checklisten-Modul ist derzeit nicht verfuegbar.</p>
    </div>
<?php endif; ?>
