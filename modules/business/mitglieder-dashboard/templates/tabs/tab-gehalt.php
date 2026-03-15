<?php
if (!defined('ABSPATH')) exit;
?>
<h3 class="dgptm-section-title">Gehaltsbarometer</h3>
<?php if (shortcode_exists('gehaltsbarometer_popup_guard')) : ?>
    <?php echo do_shortcode('[gehaltsbarometer_popup_guard id="37862"]'); ?>
<?php endif; ?>

<?php if (shortcode_exists('gehaltsbarometer')) : ?>
    <?php echo do_shortcode('[gehaltsbarometer]'); ?>
<?php endif; ?>

<?php if (shortcode_exists('gehaltsbarometer_is')) : ?>
    <?php echo do_shortcode('[gehaltsbarometer_is][gehaltsbarometer_chart][/gehaltsbarometer_is]'); ?>
<?php else : ?>
    <div class="dgptm-tab-unavailable">
        <span class="dashicons dashicons-chart-bar"></span>
        <p>Gehaltsbarometer-Modul ist derzeit nicht verfuegbar.</p>
    </div>
<?php endif; ?>
