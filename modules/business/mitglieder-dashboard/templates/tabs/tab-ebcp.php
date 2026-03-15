<?php
if (!defined('ABSPATH')) exit;
?>
<h3 class="dgptm-section-title">EBCP Delegierte</h3>
<?php if (shortcode_exists('delegierte_liste')) : ?>
    <?php echo do_shortcode('[delegierte_liste]'); ?>
<?php else : ?>
    <div class="dgptm-tab-unavailable">
        <span class="dashicons dashicons-awards"></span>
        <p>EBCP-Modul ist derzeit nicht verfuegbar.</p>
    </div>
<?php endif; ?>
