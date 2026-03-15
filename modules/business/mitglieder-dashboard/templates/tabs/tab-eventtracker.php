<?php
if (!defined('ABSPATH')) exit;
?>
<h3 class="dgptm-section-title">Eventtracker</h3>
<?php if (shortcode_exists('event_tracker')) : ?>
    <?php echo do_shortcode('[event_tracker]'); ?>
<?php else : ?>
    <div class="dgptm-tab-unavailable">
        <span class="dashicons dashicons-location"></span>
        <p>Eventtracker-Modul ist derzeit nicht verfuegbar.</p>
    </div>
<?php endif; ?>
