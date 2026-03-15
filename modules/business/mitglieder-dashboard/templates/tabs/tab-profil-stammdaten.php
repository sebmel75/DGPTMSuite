<?php
if (!defined('ABSPATH')) exit;
?>
<?php if (shortcode_exists('dgptm-daten-bearbeiten')) : ?>
    <?php echo do_shortcode('[dgptm-daten-bearbeiten]'); ?>
<?php else : ?>
    <a href="<?php echo esc_url(home_url('/mitgliedschaft/interner-bereich/daten-bearbeiten/')); ?>"
       class="dgptm-btn dgptm-btn--outline">Daten bearbeiten</a>
<?php endif; ?>
