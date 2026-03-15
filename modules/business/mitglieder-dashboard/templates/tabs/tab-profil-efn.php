<?php
if (!defined('ABSPATH')) exit;
?>
<?php if (shortcode_exists('efn_barcode_js')) : ?>
    <div class="dgptm-card">
        <h3>EFN-Barcode</h3>
        <?php echo do_shortcode('[efn_barcode_js]'); ?>
    </div>
<?php endif; ?>

<?php if (shortcode_exists('efn_label_sheet')) : ?>
    <div class="dgptm-card">
        <h3>EFN-Etiketten drucken</h3>
        <?php echo do_shortcode('[efn_label_sheet]'); ?>
    </div>
<?php endif; ?>
